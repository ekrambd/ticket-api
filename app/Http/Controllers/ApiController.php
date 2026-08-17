<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use DB;
use App\Models\User;
use Firebase\JWT\JWT;

class ApiController extends Controller
{
    public function login(Request $request)
    {   
        DB::beginTransaction();
    	try
    	{
    		$validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Please fill all requirement fields', 
                    'data' => $validator->errors()
                ], 422);  
            }

            $data = array('email'=>$request->email, 'password'=>$request->password);

            if (!$token = auth('api')->attempt($data)) {
	            return response()->json([
	                'status' => false,
	                'message' => 'Invalid email or password',
	                'token'   => "",
	                'data' => new \stdClass(),
	            ], 401);
	        }


            $user = auth('api')->user();

            if($request->has('device_token'))
            {
                $user->device_token = $request->device_token;
                $user->update();
            }

            DB::commit();

	        return response()->json([
	            'status' => true,
	            'message' => 'Login successful',
	            'token' => $token,
	            'data' => $user,
	            //'token_type' => 'bearer',
	        ]);

    	}catch(\Exception $e){
            DB::rollback();
    		return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
    	}
    }

    public function updateDeviceToken(Request $request)
    {
        try
        {   
            $validator = Validator::make($request->all(), [
                'device_token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Please fill all requirement fields', 
                    'data' => $validator->errors()
                ], 422);  
            }

            $user = auth('api')->user();
            $user->device_token = $request->device_token;
            $user->update();

            return response()->json(['status'=>true, 'message'=>'Successfully Updated']);

        }catch(\Exception $e){
            return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
        }
    }

    public function logout()
    {
        try
        {
        	auth('api')->logout();

	        return response()->json([
	            'status' => true,
	            'message' => 'Successfully logged out',
	        ]);

        }catch(\Exception $e){
    		return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
    	}
    }


    public function me()
    {
        try
        {
        	return response()->json([
	            'status' => true,
	            'data' => auth('api')->user(),
	        ]);
        }catch(\Exception $e){
    		return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
    	}
    }

    public function ticketLogs(Request $request)
    {
        try
        {   

            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Please fill all requirement fields', 
                    'data' => $validator->errors()
                ], 422);  
            }

            $per_page = $request->per_page?$request->per_page:10;

            $query = DB::connection('mysql_second')
                ->table('booking_histories');

            if($request->has('status'))
            {
                $query->where('status',$request->status);
            }
            if($request->has('from_date'))
            {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if($request->has('to_date'))
            {
                $query->whereDate('created_at','<=',$request->to_date);
            }
            $data = $query->orderBy('id','DESC')->paginate($per_page);


            $data->withPath(
                env('APP_URL')."/api/ticket-logs"
            );

            $data->getCollection()->transform(function ($item) {

                $decoded = json_decode($item->data, true);

                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }

                $item->data = $decoded;

                return $item;
            });
            return response()->json($data);     
        }catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function ticketDetails($id)
    {
        try {
            $data = DB::connection('mysql_second')
                ->table('booking_histories')
                ->where('id', $id)
                ->first();

            if (!$data) {
                return response()->json([
                    'status' => false,
                    'message' => 'Ticket not found'
                ], 404);
            }

            $decoded = json_decode($data->data, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $data->data = $decoded;

            return response()->json([
                'status' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function editTicket(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|integer',
                'status' => 'nullable|in:pending,booked',
                // 'user_name' => 'required|string',
                // 'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Please fill all requirement fields', 
                    'data' => $validator->errors()
                ], 422);  
            }

            $booking = DB::connection('mysql_second')
                ->table('booking_histories')
                ->where('id', $request->booking_id)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found',
                    'data' => new \stdClass()
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Decode booking data
            |--------------------------------------------------------------------------
            */

            $data = json_decode($booking->data, true);

            // Handle double encoded JSON
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            /*
            |--------------------------------------------------------------------------
            | Update Seat
            |--------------------------------------------------------------------------
            */

            if ($request->filled('seat_no')) {
                $data['onward']['seat_no'] = $request->seat_no;
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Update Data
            |--------------------------------------------------------------------------
            */

            $updateData = [
                'data' => json_encode(
                    json_encode($data, JSON_UNESCAPED_UNICODE),
                    JSON_UNESCAPED_UNICODE
                )
            ];

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            if ($request->has('status')) {
                if($booking->status == 'booked')
                {
                    return response()->json(['status'=>false, 'message'=>'Already booked the ticket by admin', 'data'=>new \stdClass()],400);
                }
                if($request->status == 'booked')
                {
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                      CURLOPT_URL => 'https://banglaone.services/api/service-balance-deduct.php',
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => '',
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => true,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => 'POST',
                      CURLOPT_POSTFIELDS => array('user_id' => $request->user_name,'password' => $request->password,'amount' => $booking->grand_total,'service_name' => 'bus ticket'),
                    ));

                    $response = curl_exec($curl);

                    curl_close($curl);
                }    
                $updateData['status'] = $request->status;
            }

            /*
            |--------------------------------------------------------------------------
            | Ticket File
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('ticket_file')) {

                $file = $request->file('ticket_file');

                $name = time() . '_' . $booking->id . '_' . $file->getClientOriginalName();

                $file->move(
                    public_path('uploads/tickets'),
                    $name
                );

                $updateData['ticket_file'] = 'uploads/tickets/' . $name;
            }

            /*
            |--------------------------------------------------------------------------
            | Update Database
            |--------------------------------------------------------------------------
            */

            DB::connection('mysql_second')
                ->table('booking_histories')
                ->where('id', $booking->id)
                ->update($updateData);

            /*
            |--------------------------------------------------------------------------
            | Get Updated Record
            |--------------------------------------------------------------------------
            */

            $booking = DB::connection('mysql_second')
                ->table('booking_histories')
                ->where('id', $booking->id)
                ->first();

            // if (!$data) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Ticket not found'
            //     ], 404);
            // }

            $decoded = json_decode($booking->data, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $booking->data = $decoded;

            return response()->json([
                'status' => true,
                'message' => 'Successfully Updated',
                'data' => $booking
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function myTickets(Request $request)
    {
        try
        {   

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'per_page' => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Please fill all requirement fields', 
                    'data' => $validator->errors()
                ], 422);  
            }

            $per_page = $request->per_page?$request->per_page:10;

            $query = DB::connection('mysql_second')
                ->table('booking_histories');

            if($request->has('status'))
            {
                $query->where('status',$request->status);
            }
            if($request->has('from_date'))
            {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if($request->has('to_date'))
            {
                $query->whereDate('created_at','<=',$request->to_date);
            }
            $data = $query->where('user_id',$request->user_id)->orderBy('id','DESC')->paginate($per_page);

            $data->withPath(
                env('APP_URL')."/api/my-tickets"
            );

            $data->getCollection()->transform(function ($item) {

                $decoded = json_decode($item->data, true);

                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }

                $item->data = $decoded;

                return $item;
            });
            return response()->json($data);     
        }catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function refresh()
    {
        return response()->json([
            'success' => true,
            'token' => auth('api')->refresh(),
            'token_type' => 'bearer',
        ]);
    }

    public function sendFCMPush(Request $request)
    {
        try {  

            // $validator = Validator::make($request->all(), [
            //     'device_token' => 'required',
            //     'title' => 'required|string',
            //     'body' => 'required',
            // ]);

            // if ($validator->fails()) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'The given data was invalid',
            //         'errors' => $validator->errors(),
            //     ], 422);
            // }


           $user = User::find(1);

           $device_token = $user->device_token;

           $serviceAccount = json_decode(file_get_contents(public_path('fcm/bangla-one-service-ltd-firebase-adminsdk-fbsvc-f6202308b2.json')), true);

            // Generate JWT
            $now = time();
            $jwt = JWT::encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600
            ], $serviceAccount['private_key'], 'RS256');

            // Exchange JWT for access token
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]));
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            if (!isset($data['access_token'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to get access token',
                    'error' => $response
                ], 500);
            }

            $accessToken = $data['access_token'];

            //return $accessToken;

            // FCM endpoint
            $fcmUrl = 'https://fcm.googleapis.com/v1/projects/' . $serviceAccount['project_id'] . '/messages:send';

            // Build payload
            $payload = [
                'message' => [
                    'token' => $user->device_token,
                    'notification' => [
                        'title' => "A New Booking Request",
                        'body' => "A New Booking Request, Please & Review the Booking Request",
                    ],
                    //'data' => $request->extra_data ?? []
                ]
            ];

            // Send notification
            $ch = curl_init($fcmUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $fcmResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return response()->json([
                'status' => $httpCode === 200,
                'message' => $httpCode === 200 ? 'Notification sent successfully' : 'Failed to send notification',
                'data' => json_decode($fcmResponse, true)
            ]);


        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send notification. Please try again later.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateUserBalance(Request $request)
    {
        try
        {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'amount' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Please fill all requirement fields', 
                    'data' => $validator->errors()
                ], 422);  
            }

            // $getData =   DB::connection('mysql_second')
            //     ->table('user_balances')
            //     ->where('user_id', $request->user_id)->first();

             DB::connection('mysql_second')
                ->table('user_balances')
                ->where('user_id', $request->user_id)
                ->update(['balance'=>$request->amount]);

            return response()->json(['status'=>true, 'message'=>'Successfully Updated']);
            
        }catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send notification. Please try again later.',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
