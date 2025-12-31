<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\StudentApprovalRequest;

class StudentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $approver;
    private StudentApprovalRequest $approval;
    protected $shouldSeedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create();
        $this->student->assignRole('member');

        $this->approver = User::factory()->create();
        $this->approver->assignRole('admin');

        // Create a pending approval request for testing
        $this->approval = StudentApprovalRequest::create([
            'user_id' => $this->student->id,
            'status' => 'pending',
            'documentation_url' => 'https://example.com/docs',
            'student_institution' => 'Test University',
            'verification_details' => 'Student ID verified',
            'requested_at' => now(),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function student_approval_endpoints_require_authentication()
    {
        $response = $this->getJson('/api/student-approvals/requests');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_list_student_approvals()
    {
        $response = $this->actingAs($this->approver)
            ->getJson('/api/student-approvals/requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'status',
                        'requested_at',
                    ]
                ],
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                ]
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function student_approvals_can_be_filtered_by_status()
    {
        $response = $this->actingAs($this->approver)
            ->getJson('/api/student-approvals/requests?status=pending');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_show_specific_student_approval()
    {
        $response = $this->actingAs($this->approver)
            ->getJson('/api/student-approvals/requests/' . $this->approval->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'status',
                ]
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approver_can_approve_student()
    {
        $response = $this->actingAs($this->approver)
            ->postJson('/api/student-approvals/requests/' . $this->approval->id . '/approve', [
                'admin_notes' => 'Approved - meets criteria',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approver_can_reject_student()
    {
        $response = $this->actingAs($this->approver)
            ->postJson('/api/student-approvals/requests/' . $this->approval->id . '/reject', [
                'rejection_reason' => 'Does not meet requirements',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function student_approval_requires_proper_reason_for_rejection()
    {
        $response = $this->actingAs($this->approver)
            ->postJson('/api/student-approvals/requests/' . $this->approval->id . '/reject', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_approver_cannot_approve_students()
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/student-approvals/requests/' . $this->approval->id . '/approve', [
                'admin_notes' => 'Trying to approve as student',
            ]);

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_approver_cannot_reject_students()
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/student-approvals/requests/' . $this->approval->id . '/reject', [
                'rejection_reason' => 'Trying to reject as student',
            ]);

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function student_approvals_list_supports_pagination()
    {
        $response = $this->actingAs($this->approver)
            ->getJson('/api/student-approvals/requests?page=1&per_page=20');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 20);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function student_approvals_list_supports_sorting()
    {
        $response = $this->actingAs($this->approver)
            ->getJson('/api/student-approvals/requests?sort=-requested_at');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_bulk_approve_students()
    {
        $response = $this->actingAs($this->approver)
            ->postJson('/api/student-approvals/requests/' . $this->approval->id . '/approve', [
                'admin_notes' => 'Bulk approval',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_bulk_reject_students()
    {
        $response = $this->actingAs($this->approver)
            ->postJson('/api/student-approvals/requests/' . $this->approval->id . '/reject', [
                'rejection_reason' => 'Bulk rejection',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
