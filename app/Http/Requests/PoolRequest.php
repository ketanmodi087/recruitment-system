<?php

namespace App\Http\Requests;

use App\Http\Helpers\Helper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PoolRequest extends FormRequest
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
    public function rules(): array
    {
        $rules = [
            'name' => 'required',
            Rule::unique('pool_list')->where(function ($query) {
                return $query->where('created_by', request()->input('created_by'));
            }),
        ];

        return $rules;
    }

    public function failedValidation(Validator $validator)
    {
        $errors = [
            'name' => $validator->errors()->first('name'),
        ];
        if ($errors['name']) {
            $message = $errors['name'] ? $errors['name'] : 'validation error';
        }

        Helper::sendError($message, $errors);
    }
}
