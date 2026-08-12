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
            $data = $data = DB::connection('mysql_second')
                                ->table('booking_histories')
                                ->get();

            return response()->json($data);
        }catch(\Exception $e){
            return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
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
