<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam name string required Max: 255
 * @bodyParam type string required in:wet_lab,dry_lab,greenhouse,mobile_lab
 * @bodyParam description string optional
 * @bodyParam capacity integer optional Min: 1, Max: 50
 * @bodyParam image_path string optional
 * @bodyParam equipment_list array optional
 * @bodyParam safety_requirements array optional
 * @bodyParam is_available boolean optional
 */
class CreateLabSpaceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['wet_lab', 'dry_lab', 'greenhouse', 'mobile_lab'])],
            'description' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'county' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string'],
            'equipment_list' => ['nullable', 'array'],
            'equipment_list.*' => ['string'],
            'safety_requirements' => ['nullable', 'array'],
            'safety_requirements.*' => ['string'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }

    public function bodyParameters()
    {
        return [
            'name' => ['description' => 'Lab space name', 'example' => 'Biology Lab'],
            'type' => ['description' => 'Lab type', 'example' => 'wet_lab'],
            'description' => ['description' => 'Lab description', 'example' => 'Modern biology laboratory'],
            'capacity' => ['description' => 'Lab capacity', 'example' => 20],
            'image_path' => ['description' => 'Lab image path', 'example' => 'labs/biology.jpg'],
            'equipment_list' => ['description' => 'Lab equipment', 'example' => ['projector', 'wifi', 'microscopes']],
            'safety_requirements' => ['description' => 'Safety requirements', 'example' => ['lab coat', 'goggles']],
            'is_available' => ['description' => 'Lab is available for booking', 'example' => true],
        ];
    }
}
