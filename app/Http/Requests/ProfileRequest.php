<?php

namespace App\Http\Requests;

use App\Http\Helpers\Helper;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(Request $request): array
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'country' => 'required',
            'address' => 'required',
            'phone' => 'required|numeric',
            'other_details' => 'required'
        ];
    }
    public function failedValidation(ValidationValidator $validator)
    {
        Helper::sendError('validation error', $validator->errors());
    }
}
