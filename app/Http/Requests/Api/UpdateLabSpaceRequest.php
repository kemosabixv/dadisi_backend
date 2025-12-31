<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam name string optional Max: 255
 * @bodyParam type string optional in:wet_lab,dry_lab,greenhouse,mobile_lab
 * @bodyParam description string optional
 * @bodyParam capacity integer optional Min: 1, Max: 50
 * @bodyParam image_path string optional
 * @bodyParam equipment_list array optional
 * @bodyParam safety_requirements array optional
 * @bodyParam is_available boolean optional
 */
class UpdateLabSpaceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['wet_lab', 'dry_lab', 'greenhouse', 'mobile_lab'])],
            'description' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
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
            'name' => ['description' => 'Lab space name', 'example' => 'Chemistry Lab Updated'],
            'type' => ['description' => 'Lab type', 'example' => 'dry_lab'],
            'description' => ['description' => 'Lab description', 'example' => 'Advanced chemistry laboratory'],
            'capacity' => ['description' => 'Lab capacity', 'example' => 25],
            'image_path' => ['description' => 'Lab image path', 'example' => 'labs/chemistry.jpg'],
            'equipment_list' => ['description' => 'Lab equipment', 'example' => ['projector', 'wifi', 'fume hood']],
            'safety_requirements' => ['description' => 'Safety requirements', 'example' => ['lab coat', 'goggles', 'gloves']],
            'is_available' => ['description' => 'Lab is available for booking', 'example' => true],
        ];
    }
}
