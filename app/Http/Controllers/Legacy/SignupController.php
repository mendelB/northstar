<?php

namespace Northstar\Http\Controllers\Legacy;

use Illuminate\Http\Request;
use Northstar\Models\User;
use Northstar\Services\Phoenix;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Northstar\Http\Controllers\Controller;
use Auth;

class SignupController extends Controller
{
    /**
     * Phoenix Drupal API wrapper.
     * @var Phoenix
     */
    protected $phoenix;

    /**
     * Make a new Signup controller, inject dependencies,
     * and set middleware for this controller's methods.
     * @param Phoenix $phoenix
     */
    public function __construct(Phoenix $phoenix)
    {
        $this->phoenix = $phoenix;

        $this->middleware('scope:user', ['only' => ['profile', 'store']]);
        $this->middleware('auth', ['only' => ['profile', 'store']]);
    }

    /**
     * Displays the (optionally filtered) index of signups.
     * GET /signups
     *
     * @see Phoenix /api/v1/signups endpoint
     * <https://github.com/DoSomething/phoenix/wiki/API#retrieve-a-signup-collection>
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $options = $request->query();

        // Transform user into users.
        if (! empty($options['user'])) {
            $options['users'] = $options['user'];
        }

        // If a user is specified, turn Northstar ID into Drupal ID
        if (! empty($options['users'])) {
            // Update the '?users=xxx,xxx,xxx' option to be based on Drupal ID, rather than Northstar ID
            $usersQuery = explode(',', $options['users']);
            $options['users'] = User::drupalIDForNorthstarId($usersQuery);

            $users = $this->usersForDrupalIds($options['users']);

            $results = $this->phoenix->getSignupIndex($options);
        } else {
            // Since we are not specifying a NS user, grab results from Phoenix first and get Drupal ID from response to use in query below to find user.
            $results = $this->phoenix->getSignupIndex($options);

            $drupalIds = collect($results['data'])->pluck('user.drupal_id');

            $users = $this->usersForDrupalIds($drupalIds);
        }

        // Now, fill in the 'user' object on the response before returning it.
        foreach ($results['data'] as $key => $result) {
            $drupal_id = array_get($result, 'user.drupal_id');
            $user = $users->where('drupal_id', $drupal_id)->first();

            // If Phoenix gave the expected drupal_id in the user response, replace it with our own data.
            if (! empty($user)) {
                $results['data'][$key]['user'] = [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_initial' => $user->last_initial,
                    'photo' => $user->photo,
                    'country' => $user->country,
                ];
            }
        }

        return $results;
    }

    /**
     * Displays the currently authenticated user's signups.
     * GET /profile/signups
     *
     * @see Phoenix /api/v1/signups endpoint
     * <https://github.com/DoSomething/phoenix/wiki/API#retrieve-a-signup-collection>
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function profile(Request $request)
    {
        // Get the currently authenticated Northstar user.
        $user = Auth::user();

        // Return an error if the user doesn't exist in Phoenix.
        if (! $user->drupal_id) {
            throw new HttpException(401, 'The user must have a Drupal ID to sign up for a campaign.');
        }

        $options = $request->query();
        $options['user'] = $user->drupal_id;

        return $this->phoenix->getSignupIndex($options);
    }

    /**
     * Display the specified signup.
     * GET /signups/:signup_id
     *
     * @see Phoenix /api/v1/signups/:sid endpoint
     * <https://github.com/DoSomething/phoenix/wiki/API#retrieve-a-specific-campaign-1>
     *
     * @param int $signup_id - Signup ID
     * @return \Illuminate\Http\Response
     */
    public function show($signup_id)
    {
        $result = $this->phoenix->getSignup($signup_id);

        $user = User::where('drupal_id', $result['data']['user']['drupal_id'])->first();

        // If Phoenix gave the expected drupal_id in the user response, replace it with our own data.
        if (! empty($user)) {
            $result['data']['user'] = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_initial' => $user->last_initial,
                'photo' => $user->photo,
                'country' => $user->country,
            ];
        }

        return $result;
    }

    /**
     * Create a new signup.
     * POST /signups
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws HttpException
     */
    public function store(Request $request)
    {
        // Validate endpoint parameters
        $this->validate($request, [
            'campaign_id' => 'required|integer',
            'source' => 'required',
        ]);

        // Get the currently authenticated Northstar user.
        $user = Auth::user();

        // Return an error if the user doesn't exist in Phoenix.
        if (! $user->drupal_id) {
            throw new HttpException(401, 'The user must have a Drupal ID to sign up for a campaign.');
        }

        // Phoenix returns [":signup_id"] on new signup, or [false] if a signup already exists.
        $signup = $this->phoenix->createSignup($user->drupal_id, $request->input('campaign_id'), $request->input('source'));

        // If signup already exists, try to find the right signup from the filtered /signups
        // index endpoint (via campaign & user ID). Since a campaign might have multiple signups
        // if it has more than one Campaign Run, we'll get the last (most recent) one,
        if ($signup[0] === false) {
            $signups = $this->phoenix->getSignupIndex(['user' => $user->drupal_id, 'campaigns' => $request->input('campaign_id')]);

            if (empty($signups['data'])) {
                throw new HttpException(500, 'Signup already exists, but could not display.');
            }

            // Return a "mocked" 200 individual item response.
            return response()->json(['data' => last($signups['data'])], 200);
        }

        // HACK: Since the "create signup" services endpoint returns a less-than-helpful
        // response (see above), we can use a [":signup_id"] response to get full transformed
        // version from the GET signups/:signup_id endpoint.
        $signupResponse = $this->phoenix->getSignup($signup[0]);

        // If we successfully created signup, return "show" response w/ 201
        return response()->json($signupResponse, 201);
    }

    /**
     * Queries for all specified drupal users at once.
     *
     * @param array $drupalIds
     * @return User
     */
    protected function usersForDrupalIds($drupalIds)
    {
        $query = $this->newQuery(User::class);

        // For the first `where` query, we want to limit results... from then on,
        // we want to append (e.g. `SELECT * WHERE _ OR WHERE _ OR WHERE _`)
        $firstWhere = true;
        foreach ($drupalIds as $drupalId) {
            $query->where('drupal_id', '=', $drupalId, ($firstWhere ? 'and' : 'or'));
            $firstWhere = false;
        }

        return $query->get();
    }
}
