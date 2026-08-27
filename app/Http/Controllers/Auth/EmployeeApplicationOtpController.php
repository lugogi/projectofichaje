<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PhoneOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeApplicationOtpController extends Controller
{
    public function __construct(private PhoneOtpService $otp) {}

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $result = $this->otp->send($validated['phone']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $result = $this->otp->verify($validated['phone'], $validated['code']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
