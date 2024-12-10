<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller
{

    public function deposit(Request $request)
    {
        $request->validate([
            'pay-method' => 'required|in:easymoney,superwalletz',
            'amount' => 'required|numeric',
            'currency' => 'required|in:USD,EUR',
        ]);

        $payMethod = $request->input('pay-method');
        $amount = $request->input('amount');
        $currency = $request->input('currency');

        if ($payMethod == 'easymoney') {
            return $this->processEasyMoney($amount, $currency);
        }

        if ($payMethod == 'superwalletz') {
            return $this->processSuperWalletz($amount, $currency);
        }
    }

    private function processEasyMoney($amount, $currency)
    {
        if (floor($amount) != $amount) {
            return response()->json(['error' => 'EasyMoney no acepta montos decimales'], 400);
        }
    
        $requestData = [
            'amount' => (int)$amount,
            'currency' => $currency,
        ];

        PaymentLog::create([
            'type' => 'request',  
            'platform' => 'EasyMoney',
            'payload' => json_encode($requestData), 
        ]);

        $response = Http::post('http://localhost:3000/process', $requestData);
    
        Transaction::create([
            'platform' => 'EasyMoney',
            'amount' => $amount,
            'currency' => $currency,
            'status' => $response->successful() ? 'success' : 'failed',
        ]);
    
        return $response->successful()
            ? response()->json(['message' => 'Pago procesado con éxito'])
            : response()->json(['error' => 'Error al procesar el pago'], 500);
    }

    private function processSuperWalletz($amount, $currency)
    {
        $callbackUrl = route('webhook.superwalletz');
    
        $requestData = [
            'amount' => $amount,
            'currency' => $currency,
            'callback_url' => $callbackUrl,
        ];
    
        PaymentLog::create([
            'type' => 'request',  
            'platform' => 'SuperWalletz',
            'payload' => json_encode($requestData),  
        ]);
    
        $response = Http::post('http://localhost:3003/pay', $requestData);
    
        if (!$response->successful()) {
            PaymentLog::create([
                'type' => 'error',  
                'platform' => 'SuperWalletz',
                'payload' => json_encode($response->json()),
            ]);
            return response()->json(['error' => 'Error al procesar el pago'], 500);
        }
    
        $responseData = $response->json();
        if (!isset($responseData['transaction_id'])) {
            return response()->json(['error' => 'Respuesta inválida del sistema de pago'], 500);
        }
    
        $transactionId = $responseData['transaction_id'];
    
        PaymentLog::create([
            'type' => 'response',  
            'platform' => 'SuperWalletz',
            'payload' => json_encode($responseData),
        ]);
    
        Transaction::create([
            'platform' => 'SuperWalletz',
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
        ]);
    
        return response()->json(['message' => 'Pago en proceso', 'transaction_id' => $transactionId]);
    }

    public function webhookSuperWalletz(Request $request)
    {
        $data = $request->all();
        Log::info($data);
        $transaction = Transaction::where('transaction_id', $data['transaction_id'])->first();
        // ACA ESTOY OBTENIENDO OTRO ID DE TRANSACTION POR LO TANTO NO PUEDO HACER EL UPDATE Y YA NO ME DA EL TIEMPO PARA APLICAR OTRA LOGICA 
        if ($transaction) {
            $transaction->update(['status' => 'success']);
        } else {
            PaymentLog::create([
                'type' => 'error',
                'platform' => 'SuperWalletz',
                'payload' => json_encode($data),
            ]);
        }
    }
}