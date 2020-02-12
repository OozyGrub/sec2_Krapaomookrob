<?php
namespace App\Http\Controllers\Frontend;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Payment;
//use App\Http\Controllers\omisePhp\lib\omise\OmiseCharge as omc;
//require_once (__DIR__ .'\omisePhp\lib\Omise.php');
//require_once 'path-to-library/omise-php/lib/Omise.php';


// 1
require_once dirname(__FILE__).'\omisePhp\lib\Omise.php';
/*
//2
// Cores and utilities.
require_once dirname(__FILE__).'omisePhp/lib/omise/res/obj/OmiseObject.php';
require_once dirname(__FILE__).'omisePhp/lib/omise/res/OmiseApiResource.php';

// Errors
require_once dirname(__FILE__).'omisePhp/lib/omise/exception/OmiseExceptions.php';

// API Resources.
require_once dirname(__FILE__).'/omise/OmiseCharge.php';
require_once dirname(__FILE__).'/omise/OmiseSource.php';
require_once dirname(__FILE__).'/omise/OmiseTransfer.php';





*/
use OmiseCharge;
use OmiseTransfer;
use OmiseSource;

define('OMISE_API_VERSION' , env("API_VERSION", "somedefaultvalue"));
define('OMISE_PUBLIC_KEY' ,'pkey_test_5irvp3eqbf7ybksdjlt');
define('OMISE_SECRET_KEY','skey_test_5irvp3eqkwepp9mc4kn');

class paymentGatewayController extends Controller{

    public function chargeCard(Request $request){
        $charge = OmiseCharge::create(array('amount'      => $request->input('p'),
                                            'currency'    => 'thb',
                                            'description' => 'Order-384',
                                            'ip'          => '127.0.0.1',
                                            'card'        => $request->input('omiseToken')),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);
        //dd("xcx");
        if($charge['status'] == 'failed'){
            //alert("failure");

            return view('dashboard');
         }
        else{
            //checkCourse();
            return view('banking');
        }

    }

    public function checkout(Request $request){
        $source = OmiseSource::create(array(
            'type'     => $request->input('internet_bnk'),
            'amount'   => $request->input('p'),
            'currency' => 'thb'
        ),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);
        $payment = Payment::create([
            //'user_id' => auth()->user()->id
            'user_id' => 0
        ]);

        $charge = OmiseCharge::create(array(
            'amount' => $request->input('p'),
            'currency' => 'thb',
            'return_uri' => url(sprintf("http://localhost:8000/result/%s",$payment->id)),
            'source' => $source['id']
          ),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);
          $payment->charge_id = $charge['id'];
          $payment->save();

//        //pay destination
//        redirect to
//        dd($charge['authorize_uri']);
//        ->
           // dd($source['id']);
            return redirect($charge['authorize_uri']);
        //Charge status. One of failed, expired, pending, reversed or successful.

        /*if($charge['status'] == 'failed'){
            //alert("failure");
            return view('dashboard');
         }
        else{
            //checkCourse();
            return view('result');
        }*/


    }
    public function Paid(){
        $transfer = OmiseTransfer::create(array(
            'amount' => 100000
        ),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);


    }

    public function returnPage($paymentID){
        $payment = Payment::find($paymentID);

        $result = OmiseCharge::retrieve($payment->charge_id);
        $payment->status = $result['status'];
        $payment->save();
        return view('result') -> with('sourceID', $result['status']);
    }

}
