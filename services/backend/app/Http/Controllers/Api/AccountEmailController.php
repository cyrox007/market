<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Account\AccountEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountEmailController extends Controller
{
    public function status(Request $request, AccountEmail $emails): JsonResponse
    {
        return response()->json($emails->status($request->user()));
    }

    public function verify(Request $request, AccountEmail $emails): JsonResponse
    {
        return response()->json($emails->issue($request->user()));
    }

    public function change(Request $request, AccountEmail $emails): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => AccountEmail::normalize($request->input('email'))]);
        }
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'current_password' => ['required', 'string']]);
        return response()->json($emails->issue($request->user(), $data['email'], $data['current_password']));
    }

    public function cancel(Request $request, AccountEmail $emails): JsonResponse
    {
        $emails->cancel($request->user());
        return response()->json(['status' => 'cancelled', 'message' => 'Смена почты отменена.']);
    }

    public function confirm(Request $request, AccountEmail $emails): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/^[a-zA-Z0-9]+$/']]);
        return response()->json($emails->confirm($data['token']));
    }
}
