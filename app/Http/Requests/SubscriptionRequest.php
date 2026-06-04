<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $method = strtolower($this->method());

        $rules = [];
        switch ($method) {
            case 'post':
                $rules = [
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('subscriptions')->whereNull('deleted_at')
                    ],
                    'price'     => 'required|numeric|min:0',
                    'interval' => 'required|in:month,year,free',
                    'description' => 'nullable|string|max:1000',
                ];
                break;
            case 'patch':
            case 'put':
                $subscriptionId = $this->route('subscription');
                $rules = [
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('subscriptions')
                            ->ignore($subscriptionId)// ignore current record
                            ->whereNull('deleted_at') // ignore soft-deleted records
                    ],

                    'price' => 'required|numeric|min:0',
                    // 'interval' => 'required|in:month,year,free',
                    'description' => 'nullable|string|max:1000',
                ];
                break;
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'Plan name is required.',
            'name.unique' => 'This plan name already exists.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be numeric.',
            'price.min' => 'Price must be at least 0.',
            'interval.required' => 'Interval is required.',
            'interval.in' => 'Interval must be either month or year or free.',
            'description.max' => 'Description may not be greater than 1000 characters.',
        ];
    }

     /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator) {
        $data = [
            'status' => true,
            'message' => $validator->errors()->first(),
            'all_message' =>  $validator->errors()
        ];

        if ( request()->is('api*')){
           throw new HttpResponseException( response()->json($data,422) );
        }

        if ($this->ajax()) {
            throw new HttpResponseException(response()->json($data,422));
        } else {
            throw new HttpResponseException(redirect()->back()->withInput()->with('errors', $validator->errors()));
        }
    }
}
