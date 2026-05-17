<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class Handler extends ExceptionHandler
{
    protected $levels = [
        //
    ];

    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'code' => 422,
                    'message' => '验证失败',
                    'errors' => $exception->errors(),
                ], 422);
            }

            if ($exception instanceof TokenExpiredException) {
                return response()->json([
                    'code' => 401,
                    'message' => 'Token已过期',
                ], 401);
            }

            if ($exception instanceof TokenInvalidException) {
                return response()->json([
                    'code' => 401,
                    'message' => 'Token无效',
                ], 401);
            }

            if ($exception instanceof JWTException) {
                return response()->json([
                    'code' => 401,
                    'message' => 'Token验证失败',
                ], 401);
            }

            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'code' => 401,
                    'message' => '未登录或登录已过期',
                ], 401);
            }

            if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'code' => 404,
                    'message' => '资源不存在',
                ], 404);
            }

            return response()->json([
                'code' => 500,
                'message' => $exception->getMessage(),
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
