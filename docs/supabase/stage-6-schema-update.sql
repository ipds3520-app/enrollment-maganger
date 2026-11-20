-- Stage 6 schema preparation for Supabase PostgreSQL.
-- PREPARATION ONLY: this file has not been executed against Supabase.
-- It is based on the up() methods in the Stage 6 Laravel migrations.
-- Run only after reviewing the actual public schema and taking an approved backup.
-- This script does not insert, update, or delete application rows.

BEGIN;

-- 1. Store the requested participant quota on each internship request.
-- Nullable so existing requests remain untouched and valid.
ALTER TABLE public.internship_requests
    ADD COLUMN IF NOT EXISTS participant_count INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'public.internship_requests'::regclass
          AND conname = 'internship_requests_participant_count_check'
    ) THEN
        ALTER TABLE public.internship_requests
            ADD CONSTRAINT internship_requests_participant_count_check
            CHECK (participant_count IS NULL OR participant_count > 0);
    END IF;
END;
$$;

-- 2. Create one shared registration token per request.
-- Laravel unsignedInteger maps to PostgreSQL INTEGER; the checks below enforce
-- non-negative usage and a positive quota.
CREATE TABLE IF NOT EXISTS public.shared_registration_tokens (
    id BIGSERIAL PRIMARY KEY,
    internship_request_id BIGINT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    quota INTEGER NOT NULL,
    used_count INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT shared_registration_tokens_internship_request_id_unique
        UNIQUE (internship_request_id),
    CONSTRAINT shared_registration_tokens_token_hash_unique
        UNIQUE (token_hash),
    CONSTRAINT shared_registration_tokens_internship_request_id_foreign
        FOREIGN KEY (internship_request_id)
        REFERENCES public.internship_requests (id) ON DELETE CASCADE,
    CONSTRAINT shared_registration_tokens_quota_check
        CHECK (quota > 0 AND used_count >= 0 AND used_count <= quota),
    CONSTRAINT shared_registration_tokens_status_check
        CHECK (status IN ('active', 'exhausted', 'revoked')),
    CONSTRAINT shared_registration_tokens_status_quota_check
        CHECK (
            (status = 'active' AND used_count < quota)
            OR (status = 'exhausted' AND used_count = quota)
            OR status = 'revoked'
        )
);

CREATE INDEX IF NOT EXISTS shared_registration_tokens_status_index
    ON public.shared_registration_tokens (status);

-- 3. Allow Stage 6 accounts to have no legacy token.
-- This changes only nullability. It preserves the existing foreign key and
-- UNIQUE constraint on registration_token_id; PostgreSQL permits multiple NULLs
-- under a UNIQUE constraint. No existing value is rewritten.
ALTER TABLE public.users_intern
    ALTER COLUMN registration_token_id DROP NOT NULL;

-- 4. Add optional personal profile fields to existing applicant records.
-- All are nullable so existing profiles and legacy registrations are preserved.
ALTER TABLE public.internship_applicants
    ADD COLUMN IF NOT EXISTS nik VARCHAR(16) NULL;
ALTER TABLE public.internship_applicants
    ADD COLUMN IF NOT EXISTS school_name VARCHAR(150) NULL;
ALTER TABLE public.internship_applicants
    ADD COLUMN IF NOT EXISTS address TEXT NULL;
ALTER TABLE public.internship_applicants
    ADD COLUMN IF NOT EXISTS birth_date DATE NULL;
ALTER TABLE public.internship_applicants
    ADD COLUMN IF NOT EXISTS gender VARCHAR(20) NULL;

-- 5. Record which intern claimed a shared token and whether their profile
-- completion is finished. Cascades match the Laravel migration. Existing
-- absensi and other applicant foreign keys are not modified.
CREATE TABLE IF NOT EXISTS public.shared_registration_claims (
    id BIGSERIAL PRIMARY KEY,
    shared_registration_token_id BIGINT NOT NULL,
    user_intern_id BIGINT NOT NULL,
    internship_applicant_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending_profile',
    claimed_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT shared_registration_claims_shared_registration_token_id_foreign
        FOREIGN KEY (shared_registration_token_id)
        REFERENCES public.shared_registration_tokens (id) ON DELETE CASCADE,
    CONSTRAINT shared_registration_claims_user_intern_id_foreign
        FOREIGN KEY (user_intern_id)
        REFERENCES public.users_intern (id) ON DELETE CASCADE,
    CONSTRAINT shared_registration_claims_internship_applicant_id_foreign
        FOREIGN KEY (internship_applicant_id)
        REFERENCES public.internship_applicants (id) ON DELETE CASCADE,
    CONSTRAINT shared_registration_claims_user_intern_id_unique
        UNIQUE (user_intern_id),
    CONSTRAINT shared_registration_claims_internship_applicant_id_unique
        UNIQUE (internship_applicant_id),
    CONSTRAINT shared_claim_token_user_unique
        UNIQUE (shared_registration_token_id, user_intern_id),
    CONSTRAINT shared_registration_claims_status_check
        CHECK (
            (status = 'pending_profile' AND completed_at IS NULL)
            OR (status = 'completed' AND completed_at IS NOT NULL)
        )
);

CREATE INDEX IF NOT EXISTS shared_claim_token_status_index
    ON public.shared_registration_claims (shared_registration_token_id, status);

COMMIT;

-- Review notes:
-- * The provided production snapshot says the two shared tables and
--   participant_count are absent. It does not state whether the five personal
--   fields already exist or whether registration_token_id is already nullable;
--   those must be checked in the Supabase catalog before applying this script.
-- * ADD COLUMN IF NOT EXISTS and CREATE TABLE/INDEX IF NOT EXISTS make a normal
--   rerun safe, but they do not repair a same-named object with a different
--   definition. Verify any pre-existing columns, constraints, or indexes first.
-- * Existing approved requests are intentionally not backfilled with quota or
--   shared tokens. Existing registration_tokens and users_intern data remain.
-- * PostgreSQL DDL is transactional. If run as one script in a single session,
--   an error before COMMIT should roll the DDL back. Do not split statements
--   across sessions.
