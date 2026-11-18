<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class ProposalDownloadTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
        Storage::fake('local');
    }

    /** @return array{User, Institution} */
    private function operator(string $name): array
    {
        $institution = Institution::factory()->create(['name' => $name]);

        return [User::factory()->operator($institution)->create(), $institution];
    }

    private function requestWithProposal(User $operator, Institution $institution): InternshipRequest
    {
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->id,
            'title' => 'Permohonan Magang',
            'description' => null,
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
        ]);
        $path = 'proposals/'.$request->id.'/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake proposal');
        $request->proposal_path = $path;
        $request->save();

        return $request;
    }

    public function test_admin_dapat_mengunduh_proposal_dari_seluruh_institusi(): void
    {
        $admin = User::factory()->admin()->create();
        [$operatorA, $institutionA] = $this->operator('Institusi A');
        [$operatorB, $institutionB] = $this->operator('Institusi B');
        $requestA = $this->requestWithProposal($operatorA, $institutionA);
        $requestB = $this->requestWithProposal($operatorB, $institutionB);

        $this->actingAs($admin)->get(route('admin.internship-requests.proposal', $requestA))
            ->assertOk()->assertDownload('proposal-'.$requestA->id.'.pdf');
        $this->actingAs($admin)->get(route('admin.internship-requests.proposal', $requestB))
            ->assertOk()->assertDownload('proposal-'.$requestB->id.'.pdf');
    }

    public function test_operator_hanya_dapat_mengunduh_proposal_milik_institusinya(): void
    {
        [$operator, $institution] = $this->operator('Institusi A');
        [$otherOperator, $otherInstitution] = $this->operator('Institusi B');
        $ownRequest = $this->requestWithProposal($operator, $institution);
        $otherRequest = $this->requestWithProposal($otherOperator, $otherInstitution);

        $this->actingAs($operator)->get(route('operator.internship-requests.proposal', $ownRequest))
            ->assertOk()->assertDownload('proposal-'.$ownRequest->id.'.pdf');
        $this->actingAs($operator)->get(route('operator.internship-requests.proposal', $otherRequest))
            ->assertNotFound();
    }

    public function test_guest_tidak_dapat_mengunduh_proposal(): void
    {
        [$operator, $institution] = $this->operator('Institusi A');
        $request = $this->requestWithProposal($operator, $institution);

        $this->get(route('admin.internship-requests.proposal', $request))->assertRedirect(route('admin.login'));
        $this->get(route('operator.internship-requests.proposal', $request))->assertRedirect(route('operator.login'));
    }

    public function test_pengajuan_lama_tanpa_proposal_tidak_dapat_diunduh(): void
    {
        $admin = User::factory()->admin()->create();
        [$operator, $institution] = $this->operator('Institusi Lama');
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->id,
            'title' => 'Pengajuan Lama',
            'description' => null,
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
        ]);

        $this->actingAs($admin)->get(route('admin.internship-requests.proposal', $request))->assertNotFound();
    }
}
