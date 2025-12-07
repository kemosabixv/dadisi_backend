<?php

namespace App\Http\Requests\Api\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class StudentApprovalSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'student_institution' => 'required|string|max:255',
            'student_email' => 'required|email|max:255',
            'documentation_url' => 'required|url|max:500',
            'birth_date' => 'required|date|before:' . now()->subYears(16)->toDateString(),
            'county' => 'required|string|max:50',
            'additional_notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'student_institution.required' => 'Educational institution is required',
            'student_email.required' => 'University email is required',
            'documentation_url.required' => 'Student ID documentation URL is required',
            'birth_date.before' => 'You must be at least 16 years old',
            'county.required' => 'County of residence is required',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'student_institution' => [
                'description' => 'Name of the educational institution',
                'example' => 'University of Nairobi',
            ],
            'student_email' => [
                'description' => 'University email address for verification',
                'example' => 'student@uon.ac.ke',
            ],
            'documentation_url' => [
                'description' => 'URL to student ID or verification document',
                'example' => 'https://example.com/student_id.pdf',
            ],
            'birth_date' => [
                'description' => 'Date of birth for age verification',
                'example' => '2005-01-15',
            ],
            'county' => [
                'description' => 'County of residence',
                'example' => 'Nairobi',
            ],
            'additional_notes' => [
                'description' => 'Additional information to support the request',
                'example' => 'Currently in my second year of studies',
            ],
        ];
    }
}
