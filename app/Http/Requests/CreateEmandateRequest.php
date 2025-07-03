<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmandateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Yahan aap logic daal sakte hain ki sirf admin hi isse use kar paaye.
        // For now, hum ise true rakhte hain.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'userId' => 'required|numeric|exists:customers,id',
            'amount' => 'required|numeric|min:1',
            'billDetails' => 'required|string|max:255',
            'collectionType' => 'required|in:installments,oneTime',
            'frequency' => 'required_if:collectionType,installments|in:daily,weekly,monthly,yearly',
            'installments' => 'required_if:collectionType,installments|integer|min:2|max:100',
            'startDate' => 'required|date|after_or_equal:today',
            // 'payment_method' => 'required|in:upi,nach',
        ];
    }
}