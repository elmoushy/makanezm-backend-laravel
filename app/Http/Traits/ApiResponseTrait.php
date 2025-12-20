<?php

namespace App\Http\Traits;

trait ApiResponseTrait
{
    /**
     * Recursively fix UTF-8 encoding issues in data.
     */
    private function fixUtf8($data)
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        if (is_array($data)) {
            return array_map([$this, 'fixUtf8'], $data);
        }

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                return $this->fixUtf8($data->toArray(request()));
            }
            foreach ($data as $key => $value) {
                $data->$key = $this->fixUtf8($value);
            }
        }

        return $data;
    }

    /**
     * Success response.
     *
     * @param  mixed  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = [], string $message = 'Success', int $status = 200)
    {
        return response()->json([
            'message' => $message,
            'status' => (string) $status,
            'data' => $this->fixUtf8($data),
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Error response.
     *
     * @param  mixed  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, string $code = 'ERROR', int $status = 400, $data = [])
    {
        return response()->json([
            'message' => $message,
            'status' => (string) $status,
            'data' => [
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
        ], $status);
    }

    /**
     * Created response.
     *
     * @param  mixed  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createdResponse($data = [], string $message = 'Created successfully')
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Not found response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found')
    {
        return $this->errorResponse($message, 'NOT_FOUND', 404);
    }

    /**
     * Forbidden response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Access forbidden')
    {
        return $this->errorResponse($message, 'FORBIDDEN', 403);
    }

    /**
     * Unauthorized response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized')
    {
        return $this->errorResponse($message, 'UNAUTHORIZED', 401);
    }

    /**
     * Conflict response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function conflictResponse(string $message = 'Resource already exists')
    {
        return $this->errorResponse($message, 'CONFLICT', 409);
    }

    /**
     * Validation error response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationErrorResponse(string $message = 'Validation failed', array $errors = [])
    {
        return response()->json([
            'message' => $message,
            'status' => '422',
            'data' => [
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $message,
                    'details' => $errors,
                ],
            ],
        ], 422);
    }
}
