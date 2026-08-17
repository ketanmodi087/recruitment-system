<?php

namespace App\Http\Requests;

use App\Http\Helpers\Helper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
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
        $rules = [
            'name' => 'required',
            'phone' => 'required|numeric',
        ];

        // For updating, exclude the email uniqueness check for the current agency
        if ($request->isMethod('PUT')) {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('customers')->ignore($request->route('id')),
            ];
        } else {
            // For adding new records, include the email uniqueness check
            $rules['email'] = 'required|email|unique:customers,email|unique:agencies,email';
        }

        return $rules;
    }

    public function failedValidation(Validator $validator)
    {
        $errors = [
            'email' => $validator->errors()->first('email'),
            'password' => $validator->errors()->first('password'),
        ];
        if ($errors['email']) {
            $message = $errors['email'] ? $errors['email'] : 'validation error';
        }
        if ($errors['password']) {
            $message = $errors['password'] ? $errors['password'] : 'validation error';
        }
        Helper::sendError($message, $errors);
    }
}
