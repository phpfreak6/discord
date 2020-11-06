<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Http\Requests\Admin\ClientRegister;
use Illuminate\Http\Request;
use Validator;
use App\Configuration;
use App\User;
use Auth;
class DiscordController extends Controller
{


    

    /**
     * Redirect the user to Discord's authorization page 
     *
     * User Login System
     *
     * @return \Illuminate\Http\Response
     */
    public function authorize2(ClientRegister $request)
    {


        $params = array(

            'client_id' => env('DISCORD_CLIENT_ID'),
            'redirect_uri' => env('REDIRECT_URI'),
            'response_type' => 'code',
            'scope' => 'identify guilds'
        );

        $request->session()->put('client_email', $request->email);

        return redirect(env('AUTHORIZE_URL').'?'.http_build_query($params));

    }
	
	/**
     * Link Discord to User Account
     * query string
     * @return \Illuminate\Http\Response
     */

    public function linkDiscord(Request $request)
    {


         $params = array(

            'client_id' => env('DISCORD_CLIENT_ID'),
            'redirect_uri' => env('REDIRECT_URI'),
            'response_type' => 'code',
            'scope' => 'identify guilds'
        );


        return redirect(env('AUTHORIZE_URL').'?'.http_build_query($params));

    }

    /**
     * When Discord redirects the user back here, there will be a "code" and "state" parameter in the 
     * query string
     * @return \Illuminate\Http\Response
     */
    public function afterRedirectGetCode()
    {
        
           
        $token = $this->apiRequest(env('TOKEN_URL'), array(
            "grant_type" => "authorization_code",
            'client_id' => env('DISCORD_CLIENT_ID'),
            'client_secret' => env('DISCORD_SECRET_ID'),
            'redirect_uri' => env('REDIRECT_URI'),
            'code' => request()->get('code')
          ));


        if(request()->get('error')== 'access_denied'){

            if(Auth::check()) { 
                $userRole = auth()->user();
                $role = $userRole->roles->first()->name;

                if($role == 'admin'){
                    return redirect()->route('admin.configurations');
                }else{
                    return redirect('/');
                }
            }else{
                return redirect('/');
            }

        }
        session()->put('access_token',$token->access_token);

        $user = $this->apiRequest(env('API_URL_BASE'));
        
        if(Auth::check()) { 
            $userRole = auth()->user();
            $role = $userRole->roles->first()->name;

            if($role == 'admin'){
                
                //Insert Discord data to Database
                Configuration::linkDiscord($user);

                session()->flash('success','Account Linked Successfully.');
                return redirect()->route('admin.configurations');

            }
        }
    
        $userExist = User::where('discord_id',$user->id)->first();
        

        if(!empty($userExist)){
            
            auth()->login($userExist);
             
            return redirect(RouteServiceProvider::CLIENT);

            
        }else{

            $ifPreCreated = User::where(['discord_username' => $user->username , 'discord_discriminator' => $user->discriminator ])->first();
            
            if(!empty($ifPreCreated)){
              
              $ifPreCreated->discord_id = $user->id;

              $ifPreCreated->discord_avatar = $user->avatar;

              $ifPreCreated->save();

              auth()->login($ifPreCreated);

            }else{

               $this->createUser($user, request()->session()->get('client_email'));
            }

            return redirect(RouteServiceProvider::CLIENT);

        }


    }

    /**
     * Create user by Discord Auth API
     * query string
     * @return \Illuminate\Http\Response
     */
    public  function createUser($data,$email){

            $user = new User;
            $user->email = $email;
            $user->first_name = $data->username;
            $user->discord_id = $data->id;
            $user->discord_username = $data->username;
            $user->discord_avatar = $data->avatar;
            $user->discord_discriminator = $data->discriminator;
            $user->save();
            $user->assignRole('client');

            auth()->login($user);

            

    }


    function apiRequest($url, $post=FALSE, $headers=array()) {

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

      $response = curl_exec($ch);


      if($post){

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

      $headers[] = 'Accept: application/json';
      }
      if(session()->get('access_token')){
        $headers[] = 'Authorization: Bearer ' . session()->get('access_token');

      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      }
      $response = curl_exec($ch);
      return json_decode($response);
    }
 
    /**
     * Deatach Discord Account
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function deatach()
    {

        Configuration::where('name','discord')->update(['value' => null]);

        session()->flash('success','Account Deatach Successfully.');

        return redirect()->back();


    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
