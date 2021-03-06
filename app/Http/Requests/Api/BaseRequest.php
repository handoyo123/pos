<?php

namespace App\Http\Requests\Api;

use Examyou\RestAPI\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;

class BaseRequest extends FormRequest
{
    /**
     * @throws ApiException
     */
    protected function failedAuthorization()
    {
        throw new ApiException('This action is unauthorized.');
    }
}
