<?php

namespace App\Http\Requests;

use App\Http\Helpers\Helper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class LoginRequest extends FormRequest
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
            'email' => 'required|email|exists:agencies,email',
            'password' => 'min:6|required'
        ];
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
