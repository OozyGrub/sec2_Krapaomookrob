<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\PaymentRequest;

class PaymentRequestController extends Controller
{
    
    public function tutorIndex(){
        if(auth()->user()->isTutor()) return view('tutor_payment_request');
        abort(401, "User can't perform this actions");
    }

    public function adminIndex(){
        if(auth()->user()->isAdmin() | auth()->user()->isSuperuser()) return view('admin_payment_request');
        abort(401, "User can't perform this actions");
    }
    
    public function create(Request $request){
        
        $validatedData = $request->validate([
            'amount' => 'required|integer|between:1,' . auth()->user()->balance,
        ]);

        if(!auth()->user()->BankAccount){
            return response(['message' => 'You need to add bank account information.', 'errors' => ['bankAccount' => ['Bank account information is required']]] ,422);
        }

        PaymentRequest::create([
            'amount' => $request->amount,
            'requested_by' => auth()->user()->id,
            'bank_account' => auth()->user()->BankAccount->id
        ]);
        
        return response("Create payment request successfully.",200);
    }

    public function getMyRequests(){
        return response(auth()->user()->requestPaymentRequests()->orderBy('created_at','DESC')->get(), 200);
    }

    public function getInitRequests(){
        return response(PaymentRequest::where('status', '=', 'init')->get(), 200);
    }
}
