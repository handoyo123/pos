<?php

namespace App\Http\Requests\Api\Order;

use Illuminate\Foundation\Http\FormRequest;

class PosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     * @noinspection PhpUndefinedFieldInspection
     */
    public function rules(): array
    {
        $rules = [
            'amount' => 'numeric',
        ];

        if ($this->amount > 0) {
            $rules['payment_mode_id'] = 'required';
        }

        return $rules;
    }
}
