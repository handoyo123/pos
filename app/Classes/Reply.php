<?php

namespace App\Classes;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Validation\Validator;

/**
 * Class Reply
 * @package App\Classes
 */
class Reply
{

    /**
     * @param $messageOrData
     * @param null $data
     * @return array
     */
    public static function success($messageOrData, $data = null): array
    {
        $response = [
            'status' => 'success'
        ];

        if (!empty($messageOrData) && !is_array($messageOrData)) {
            $response['message'] = Reply::getTranslated($messageOrData);
        }

        if (is_array($data)) {
            $response = array_merge($data, $response);
        }

        if (is_array($messageOrData)) {
            $response = array_merge($messageOrData, $response);
        }

        return $response;
    }

    /**
     * @param $message
     * @return Translator|string
     */
    private static function getTranslated($message)
    {
        $trans = trans($message);

        if ($trans == $message) {
            return $message;
        } else {
            return $trans;
        }
    }

    /**
     * @param $message
     * @param null $errorName
     * @param array $errorData
     * @return array
     */
    public static function error($message, $errorName = null, array $errorData = []): array
    {
        return [
            'status' => 'fail',
            'error_name' => $errorName,
            'data' => $errorData,
            'message' => Reply::getTranslated($message)
        ];
    }

    /** Return validation errors
     * @param \Illuminate\Validation\Validator|Validator $validator
     * @return array
     */

    public static function formErrors($validator): array
    {
        return [
            'status' => 'fail',
            'errors' => $validator->getMessageBag()->toArray(),
            'message' => 'Please make sure to fill all the necessary fields.'
        ];
    }

    /** Response with redirect action. This is meant for ajax responses and is not meant for direct redirecting
     * to the page
     * @param $url string to redirect to
     * @param null $message Optional message
     * @return array
     */

    public static function redirect(string $url, $message = null): array
    {
        if ($message) {
            return [
                'status' => 'success',
                'message' => Reply::getTranslated($message),
                'action' => 'redirect',
                'url' => $url
            ];
        } else {
            return [
                'status' => 'success',
                'action' => 'redirect',
                'url' => $url
            ];
        }
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function dataOnly($data)
    {
        return $data;
    }

    public static function successWithData($data): array
    {
        $response = [
            'status' => 'success'
        ];

        $response['data'] = $data;

        return $response;
    }

}
