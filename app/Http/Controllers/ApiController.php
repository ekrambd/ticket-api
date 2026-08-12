<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use DB;

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
}
