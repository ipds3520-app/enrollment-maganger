<?php

namespace Tests\Unit;

use ReflectionClass;
use Tests\TestCase;

class ActiveRegistrationTokenIndexCatalogTest extends TestCase
{
    public function test_catalog_validator_accepts_expected_index_in_public_schema(): void
    {
        $row = [
            'schema_name' => 'public',
            'active_schema' => 'public',
            'table_name' => 'registration_tokens',
            'index_name' => 'registration_tokens_one_active_per_applicant_unique',
            'index_method' => 'btree',
            'is_unique' => 1,
            'is_valid' => 1,
            'is_ready' => 1,
            'is_live' => 1,
            'total_columns' => 1,
            'key_columns' => 1,
            'columns' => 'applicant_id',
            'predicate' => "((status)::text = 'active'::text)",
        ];

        $this->assertTrue($this->matchesCatalogRow($row));

        $row['predicate'] = "((status)::character varying = 'active'::character varying)";
        $this->assertTrue($this->matchesCatalogRow($row));
    }

    public function test_catalog_validator_rejects_same_name_with_a_different_definition(): void
    {
        $validRow = [
            'schema_name' => 'public',
            'active_schema' => 'public',
            'table_name' => 'registration_tokens',
            'index_name' => 'registration_tokens_one_active_per_applicant_unique',
            'index_method' => 'btree',
            'is_unique' => 1,
            'is_valid' => 1,
            'is_ready' => 1,
            'is_live' => 1,
            'total_columns' => 1,
            'key_columns' => 1,
            'columns' => 'applicant_id',
            'predicate' => "((status)::text = 'active'::text)",
        ];

        foreach ([
            ['schema_name' => 'private'],
            ['table_name' => 'other_table'],
            ['index_name' => 'registration_tokens_applicant_id_unique'],
            ['index_method' => 'hash'],
            ['is_unique' => 0],
            ['is_valid' => 0],
            ['is_ready' => 0],
            ['is_live' => 0],
            ['total_columns' => 2],
            ['key_columns' => 2],
            ['columns' => 'token_hash'],
            ['predicate' => "((status)::text = 'revoked'::text)"],
            ['predicate' => null],
        ] as $difference) {
            $this->assertFalse($this->matchesCatalogRow(array_replace($validRow, $difference)));
        }
    }

    /** @param array<string, int|string|null> $row */
    private function matchesCatalogRow(array $row): bool
    {
        $migration = require database_path('migrations/2026_11_18_000002_allow_registration_token_history_per_applicant.php');
        $method = (new ReflectionClass($migration))->getMethod('isExpectedActiveApplicantIndex');
        $method->setAccessible(true);

        return $method->invoke($migration, (object) $row);
    }
}
