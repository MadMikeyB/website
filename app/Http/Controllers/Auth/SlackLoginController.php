<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\Slack;
use Illuminate\Http\Request;
use Log;
use Auth;
use Socialite;
use App\Models\User;
use App\Http\Controllers\Controller;

class SlackLoginController extends Controller
{
    /**
     * Redirect the user to the Slack authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('slack')->redirect();
    }

    /**
     * Obtain the user information from Slack.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback()
    {
        $slackUser = Socialite::driver('slack')->user();

        if ($user = User::where('email', $slackUser->getEmail())->first()) { // User already exists, so sign them back in

            $user->update([
                'name' => $slackUser->getName(),
                'avatar' => $slackUser->getAvatar(),
                'nickname' => $slackUser->getNickname(),
                'slack_id' => $slackUser->getId()
            ]);

            Log::debug("{$slackUser->getName()} logged in with Slack");
        } else { // User does not exist, so register them

            $user = User::create([
                'slack_id' => $slackUser->getId(),
                'name' => $slackUser->getName(),
                'email' => $slackUser->getEmail(),
                'avatar' => $slackUser->getAvatar(),
                'nickname' => $slackUser->getNickname(),
                'password' => bcrypt(str_random(16)),
            ]);

            Log::debug("{$slackUser->getName()} registered with Slack");
        }

        Auth::login($user);

        return redirect()->route('home');
    }

    public function showInviteForm() {
        return view('frontend.slack');
    }

    public function sendInvite(Request $request) {

        $request->validate([
            'email' => 'required'
        ]);
        // TODO: update to the check response and return success
        // or error message
        $slack = Slack::sendInvitation($request->input('email'));
        if(!$slack->ok) {
            $message = '';
            switch($slack->error) {
                case 'already_in_team':
                    $message = 'You have already joined!';
                    break;
                case 'already_invited':
                    $message = 'You have already been invited!';
                    break;
                case 'invalid_email':
                    $message = 'The email submitted is invalid.';
                    break;
                default:
                    'There was a problem sending your invite.';
                    break;
            }
            return back()->withErrors(['message' => $message]);
        }
        return back()->withSuccess('Yay! Your invite is on the way. We can\'t wait to welcome you the UK\'s best Laravel community!');
    }
}
