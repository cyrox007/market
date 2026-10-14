<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\PhoneNumberRule;
use App\Services\Order\OneClickLeadService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OneClickLeadController extends Controller
{
    public function __invoke(Request $request, OneClickLeadService $leads): JsonResponse
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone')), 'idempotency_key' => $request->header('Idempotency-Key')]);
        $data = $request->validate(['name' => 'required|string|max:255', 'phone' => ['required', new PhoneNumberRule],
            'comment' => 'nullable|string|max:1000', 'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:products,id', 'quantity' => 'required|integer|min:1|max:1000',
            'region_id' => 'nullable|integer|exists:shipping_locations,id', 'accept_offer' => 'required|accepted',
            'accept_personal_data' => 'required|accepted', 'website' => 'prohibited', 'idempotency_key' => 'nullable|string|min:16|max:128']);
        [$lead, $created] = $leads->create($data, $data['idempotency_key'] ?? null, $request->ip());

        return response()->json(['number' => $lead->number, 'status' => $lead->status, 'duplicate' => ! $created,
            'message' => 'Заявка принята. Менеджер свяжется с вами.'], $created ? 201 : 200);
    }
}
