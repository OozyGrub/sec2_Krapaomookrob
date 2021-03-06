<?php
namespace App\Http\Controllers\Frontend;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

use App\Payment;
use App\course;
use App\Cart;
use App\User;
use App\CourseStudent;
use App\PaymentRequest;
use App\Advertisement;

use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Cookie;
use App\BankAccount;
use App\CourseRequester;
use App\Http\Controllers\AdvertisementController;
use App\OmiseRecipientAccount;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseRequesterController;
use App\RefundRequest;
use App\Http\Controllers\UserController;

require_once dirname(__FILE__).'/../../../../vendor/autoload.php';

use OmiseCharge;
use OmiseTransfer;
use OmiseSource;
use OmiseRecipient;
define('OMISE_API_VERSION' , env("OMISE_API_VERSION", null));
define('OMISE_PUBLIC_KEY' , env("OMISE_PUBLIC_KEY", null));
define('OMISE_SECRET_KEY', env("OMISE_SECRET_KEY", null));
define('WEBSITE', env("APP_URL", null));

class paymentGatewayController extends Controller{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function cartToPayment(Request $request){
        //create payment
        $payment = Payment::create([
            'user_id' => auth()->user()->id
        ]);

        $totalprice = 0;
        //create cart
        foreach ($request->input('course_id') as $value)
        {
            if($value == null) continue;
            $courseStudentCount = CourseStudent::where('course_id',$value)->count();
            if($courseStudentCount !=0 ){
                $courseStudent = CourseStudent::where('course_id',$value)->get();
                if($courseStudent[0]->status == 'registered') continue;
            }
            $totalprice = $totalprice + (Course::find($value))->price;
            Cart::create([
                    'payment_id' => $payment->id,
                    'course_id' =>  $value
            ]);
        }

        $cookie = Cookie::forget(CartController::getUserCart(auth()->user()->id,$request)[0]);
        return response()->json([
                'payment_id' => $payment->id,
                'totalprice' => $totalprice
              ])->withCookie($cookie);
    }


    public function chargeCard(Request $request){
        $pay2 = Payment::where('id',$request->input('paymentID'))->first();

        if(!$request->input('isAdvertisement')){
            if( $pay2->status=="successful"){
                return view('dashboard')->with('error','This payment has already been paid');
            }
            if($pay2->status == 'pending'){
                this.returnPage($request->input('paymentID'),$request->input('isAdvertisement'),$request->input('courseId'));
            }
            if( $this->checkBeforeCharge($request->input('paymentID'))){
                return view('dashboard')->with('error','Some course is taken');
            }
        }
        else{
            if($request->input('p')==0 || $request->input('p') != 50000){
                return view('dashboard')->with('error','Totalprice is incorrect');
            }
        }
        $paymentId = $request->input('paymentID');
        $charge = OmiseCharge::create(array('amount'      => $request->input('p'),
                                            'currency'    => 'thb',
                                            'description' => 'Order-384',
                                            'ip'          => '127.0.0.1',
                                            'card'        => $request->input('omiseToken')),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);

        if($request->input('isAdvertisement')){
            $userId = auth()->user()->id;
            $payment = Payment::create(['user_id' => $userId]);
            $paymentId  = $payment->id;
        }

        $payment = Payment::find($paymentId);
        $payment->charge_id = $charge['id'];
        $payment->pay_by_card = true;
        $payment->save();
        return $this->returnPage($paymentId,$request->input('isAdvertisement'),$request->input('courseId'));

    }
    public function checkBeforeCharge($paymentID){
        $arr = Cart::where('payment_id',$paymentID)->get();

        foreach ($arr as $value)
        {
            if($value == null) continue;
            $courseStudentCount = CourseStudent::where('course_id',$value['course_id'])->count();
            if($courseStudentCount !=0 ){
                $courseStudent = CourseStudent::where('course_id',$value['course_id'])->get();
                if($courseStudent[0]->status == 'registered'){
                    return true;
                }
            }
        }
        return false;
    }
    public function checkPrice($pid,$totalprice){
        $carts=Cart::where('payment_id',$pid)->get();
        $tp = 0;
        foreach ($carts as $value){
          $tp = $tp + (Course::find($value->course_id))->price;
        }

      return $tp == $totalprice ? false:true;
  }

    public function checkout(Request $request){

        $pay2 = Payment::where('id',$request->input('paymentID'))->first();

        if(!$request->input('isAdvertisement')){
            if( $pay2->status=="successful"){
                return view('dashboard')->with('error','This payment has already been paid');
            }
            if($pay2->status == 'pending'){
                this.returnPage($request->input('paymentID'),$request->input('isAdvertisement'),$request->input('courseId'));
            }
            if( $this->checkBeforeCharge($request->input('paymentID'))){
                return view('dashboard')->with('error','Some course is taken');
            }
        }
        else{
            if($request->input('p') != 50000){
                return view('dashboard')->with('error','Totalprice is incorrect');
            }
        }
        $paymentId = $request->input('paymentID');
        $source = OmiseSource::create(array(
            'type'     => $request->input('internet_bnk'),
            'amount'   => $request->input('p'),
            'currency' => 'thb'
        ),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);

        // for advertisement
        if($request->input('isAdvertisement')){
            $userId = auth()->user()->id;
            $payment = Payment::create(['user_id' => $userId]);
            $paymentId = $payment->id;
          }

        $charge = OmiseCharge::create(array(
            'amount' => $request->input('p'),
            'currency' => 'thb',
            'return_uri' => url(sprintf("%s/result/%s/%s/%s",WEBSITE,$paymentId,$request->input('isAdvertisement')==null ? 0:1,$request->input('courseId')==null ? 0:$request->input('courseId'))),
            'source' => $source['id']
          ),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);

          $payment = Payment::find($paymentId);
          $payment->charge_id = $charge['id'];
          $payment->pay_by_card = false;
          $payment->save();

            return redirect($charge['authorize_uri']);

    }
    public function createTransferOmise(Request $request){
        $payReq = PaymentRequest::find($request->input('paymentReqID'));
        if($payReq == null) {
            return response('Request ID is incorrect' , 403);
        }
        if($payReq['omise_id']!=null){
            if($payReq['status'] == 'successful'){
            return response('Request ID ' . $payReq['id'] . ' is already transferred' , 403);
            }
            else{
                $http = sprintf("https://dashboard.omise.co/test/transfers/%s",$payReq['omise_id']);
            return response($http, 200);
            }
        }
        $user = User::find($payReq['requested_by']);
        $money = $payReq['amount'];
        $recipient1 = OmiseRecipientAccount::where('user_id',$payReq['requested_by'])->count();

        if($recipient1==0){
            $bank = BankAccount::find($payReq['bank_account']);
            $rec1 = OmiseRecipient::create(array('name'     => $user['name'],
                                                  'description'   => '',
                                                  'email'         => $user['email'],
                                                  'type'          => 'individual',
                                                  'bank_account'  => array( 'brand'   => $bank['bank'],
                                                                            'number'  => $bank['account_number'],
                                                                            'name'    => $bank['account_name'])));
            OmiseRecipientAccount::create([
                'user_id' => $user['id'],
               'recipient' => $rec1['id']
            ]);

        }
        $recipient1 = OmiseRecipientAccount::where('user_id',$payReq['requested_by'])->get();

        $transfer = OmiseTransfer::create(array(
            'amount' => $money*100,
            'recipient' => $recipient1[0]['recipient']
        ),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);
        //update transfer
        $payReq->omise_id = $transfer['id'];
        $payReq->transferred_by = auth()->user()->id;
        $payReq->save();

        $http = sprintf("https://dashboard.omise.co/test/transfers/%s",$transfer['id']);

        return response($http, 200);

    }

    public function checkTransfer(Request $request){

        // qeury data
        $arr1 =  PaymentRequest::where('status','!=', 'successful')->get();
        foreach($arr1 as $payReq){
            if($payReq['omise_id']==null) continue;

            $result = OmiseTransfer::retrieve($payReq['omise_id']);

            if($result['paid']){

                $payReq->status = "successful" ;
                $payReq->save();


                NotificationController::createNotification($payReq['requested_by'],'Transfer money',
                sprintf("money: %s to your account",$payReq['amount']));

            }
            else if($result['sent']){
                $payReq->status = "pending";
                $payReq->save();
            }

        }
        $arr =  PaymentRequest::where('status','!=', 'successful')->get();
        $data = response()->json($arr);
        // dd($data);
        return response($arr, 200);

    }

    public function returnPage($paymentID, $isAdvertisement,$courseId){
        // Note: courseId is for ads

        $payment = Payment::find($paymentID);
        $user = auth()->user();


        //change status in payment
        $result = OmiseCharge::retrieve($payment->charge_id);
        $payment->status = $result['status'];
        $payment->save();
        if($result['status'] == "failed"){
            return view('dashboard')->with('error','Fail to pay');
        }
        else{
            //create taken course
           // $cart = Cart::select('payment_id',$paymentID);
            if (!$isAdvertisement){
                $cart = Cart::select('course_id')
                            ->where('payment_id',$paymentID)->get();


                foreach ($cart as $value) {

                    // register course
                    auth()->user()->registeredCourses()->attach(Course::find($value->course_id));

                    //Notification
                    $user = auth()->user();
                    $user_id = $user->id;
                    $course = Course::find($value)->first();
                    $tutor_id = $course->user_id;
                    $user_name = $user->name;
                    $title = "Course registration";
                    $user_message = "New course has been registered successful.";
                    $tutor_message = "{$user_name} have register your course.";
                    NotificationController::createNotification($user_id, $title, $user_message);
                    NotificationController::createNotification($tutor_id, $title, $tutor_message);
                    if ($course->isMadeByStudent()){
                        CourseRequesterController::confirmAcceptedRequest($course->id);
                        $realUserId = CourseRequester::where('course_id','=',$course->id)->where('status','=','Accepted')->get()->first()->requester_id;
                        UserController::addBalance($realUserId, $course->price);
                    }
                    else{
                        UserController::addBalance($course->user_id, $course->price);
                    }
                }
            }else{
                $status = AdvertisementController::createAdvertisement($courseId,$user->id);
                if (!$status){
                    abort(500,'something went wrong please create report to admin.');
                }
            }
            return view('dashboard')->with('alert','Successful');
        }
    }

    public function getPaymentPage($payment_id,$totalprice){
        if($payment_id <= 0){
            return view('dashboard')->with('error','This payment is incorrect');
        }
        $pay1 = Payment::where('id',$payment_id)->count();
        if($pay1 <= 0){
            return view('dashboard')->with('error','This payment is incorrect');
        }
        $pay2 = Payment::where('id',$payment_id)->first();
        if($pay2->user_id != auth()->user()->id){
            return view('dashboard')->with('error','this is not your payment');
        }
        if($totalprice<0 || $this->checkPrice($payment_id,$totalprice)){
            return view('dashboard')->with('error','Totalprice is incorrect');
        }


        $cartList =  Cart::where('payment_id',$payment_id)->get();

        $courses = array();
        $tutor = array();
        $subject = array();
      foreach($cartList as $item){
                    $course_id = $item['course_id'];
                    $course = CourseController::getCourseInfo(intval($course_id));
                    array_push($courses,$course);

                }

        return view('/payment', ['responses'=> $courses,'payment_id'=> $payment_id, 'totalprice' => $totalprice, 'isAdvertisement' => false, 'course_id' => null]);
    }

    public function getAdsPaymentPage(Request $request) {
        $courseId = $request->input('courseId');
        $course = Course::find($courseId);
        if($course==null || $course->user_id != auth()->user()->id){
            return view('dashboard')->with('error','Your payment is incorrect. Please do it again.');
        }
        if (!Advertisement::where('course_id','=',$courseId)->get()->isEmpty()){
            return view('dashboard')->with('error','This course has already been promoted.');
        }
        $latestEntry = Payment::latest('created_at')->first();

        return view('/payment', [
            'isAdvertisement' => true,
            'totalprice' => 500,
            'payment_id' => $latestEntry==null ? 1:$latestEntry->id,
            'course_id' => $courseId
        ]);

    }

    public function refundPayment(Request $request) {
        $refundReq = RefundRequest::find($request->input('refundReq_id'));
        $charge_id = Payment::find($refundReq->payment_id)->charge_id;
        $charge = OmiseCharge::retrieve($charge_id);
        if($charge['refundable']) {
            $refund = $charge->refunds()->create(array('amount' => $refundReq->amount));
            $refundReq['omise_id'] = $refund['id'];
            $refundReq->save();
            return response(200);
        }
        return $this->createTransferOmiseRefund($refundReq);

    }
    public function createTransferOmiseRefund($refundReq){
        if($refundReq['omise_id']!=null){
            if($refundReq['status'] == 'successful'){
            return response('Request ID ' . $refundReq['id'] . ' is already transfer' , 403);
            }
            else{
                $http = sprintf("https://dashboard.omise.co/test/transfers/%s",$refundReq['omise_id']);
            return response(array('is_transferred'=>true,'data'=>$http), 200);
            }
        }
        $user = User::find($refundReq->user_id);
        $money = $refundReq->amount;
        $recipient1 = OmiseRecipientAccount::where('user_id',$refundReq->user_id)->count();

        if($recipient1==0){
            $bank = BankAccount::where('user_id',$refundReq->user_id)->first();
            $rec1 = OmiseRecipient::create(array('name'     => $user['name'],
                                                  'description'   => '',
                                                  'email'         => $user['email'],
                                                  'type'          => 'individual',
                                                  'bank_account'  => array( 'brand'   => $bank['bank'],
                                                                            'number'  => $bank['account_number'],
                                                                            'name'    => $bank['account_name'])));
            OmiseRecipientAccount::create([
                'user_id' => $user['id'],
               'recipient' => $rec1['id']
            ]);

        }

        $recipient1 = OmiseRecipientAccount::where('user_id',$refundReq->user_id)->get();

        $transfer = OmiseTransfer::create(array(
            'amount' => $money*100,
            'recipient' => $recipient1[0]['recipient']
        ),OMISE_PUBLIC_KEY,OMISE_SECRET_KEY);
        //update transfer
        $refundReq->omise_id = $transfer['id'];
        $refundReq->is_transferred = true;
        $refundReq->save();

        $http = sprintf("https://dashboard.omise.co/test/transfers/%s",$transfer['id']);
        return response(array('is_transferred'=>true,'data'=>$http), 200);

    }

    public static function checkRefund() {
        // query data
        $arr1 =  RefundRequest::where('status','!=', 'successful')->get();
        foreach($arr1 as $refundReq){
            if($refundReq['omise_id']==null) continue;

            if($refundReq['is_transferred']) {
                $result = OmiseTransfer::retrieve($refundReq['omise_id']);

                if($result['paid']){

                    $refundReq->status = "successful" ;
                    $refundReq->save();

                    $courseStudent = CourseStudent::where('user_id',$refundReq->user_id)
                                                  ->where('course_id',$refundReq->course_id)->first();
                    $courseStudent->status = 'cancelled';
                    $courseStudent->save();



                    NotificationController::createNotification($refundReq['user_id'],'Refund',
                    sprintf("Refund: %s Baht by Transfer",$refundReq['amount']));

                }
                else if($result['sent']){
                    $refundReq->status = "pending";
                    $refundReq->save();
                }
            }

            else {
                $charge_id = Payment::find($refundReq->payment_id)->charge_id;
                $charge = OmiseCharge::retrieve($charge_id);
                $result = $charge->refunds()->retrieve($refundReq['omise_id']);
                if($result['object']=='refund') {
                    $refundReq->status = "successful" ;
                    $refundReq->save();

                    $courseStudent = CourseStudent::where('user_id',$refundReq->user_id)
                                                  ->where('course_id',$refundReq->course_id)->first();
                    $courseStudent->status = 'cancelled';
                    $courseStudent->save();

                    NotificationController::createNotification($refundReq['user_id'],'Refund',
                    sprintf("Refund: %s Baht by Card",$refundReq['amount']));
                }
            }
        }
        $arr = RefundRequest::get();
        return response($arr, 200);
    }
}
