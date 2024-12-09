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
            'amount' => $amount,
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
    
        $response = Http::post('http://localhost:3000/pay', $requestData);
    
        // Registrar la respuesta en el log
        PaymentLog::create([
            'type' => 'response',  
            'platform' => 'SuperWalletz',
            'payload' => json_encode($response->json()),
        ]);
    
        Transaction::create([
            'platform' => 'SuperWalletz',
            'amount' => $amount,
            'currency' => $currency,
            'status' => $response->successful() ? 'pending' : 'failed',
        ]);
    
        return $response->successful()
            ? response()->json(['message' => 'Pago en proceso'])
            : response()->json(['error' => 'Error al procesar el pago'], 500);
    }

    public function webhookSuperWalletz(Request $request)
    {
        $data = $request->all();

        $transaction = Transaction::where('transaction_id', $data['transaction_id'])->first();
        if ($transaction) {
            $transaction->update(['status' => 'success']);
        }

        return response()->json(['message' => 'Webhook recibido']);
    }
}