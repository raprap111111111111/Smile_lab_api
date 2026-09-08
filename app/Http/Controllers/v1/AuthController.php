<?php

namespace App\Http\Controllers\v1;

use App\Domain\Auth\Actions\LoginUserAction;
use App\Domain\Auth\Actions\RegisterUserAction;
use App\Domain\Auth\Actions\SocialLoginAction;
use App\Domain\Auth\Actions\UpdatePasswordAction;
use App\Domain\Auth\DTOs\SocialLoginDTO;
use App\Domain\Auth\Mappers\LoginMapper;
use App\Domain\Auth\Mappers\RegisterMapper;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\Auth\LoginRequest;
use App\Http\Requests\v1\Auth\RegisterRequest;
use App\Http\Requests\v1\Auth\SocialLoginRequest;
use App\Http\Requests\v1\Auth\UpdatePasswordRequest;
use App\Http\Resources\v1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    private const REFRESH_TOKEN_EXPIRY = 43200; // 30 days in minutes

    public function __construct(
        private readonly LoginUserAction $loginAction,
        private readonly UpdatePasswordAction $passwordAction,
        private readonly RegisterUserAction $registerAction
    ) {}

    /**
     * Public patient self-registration
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->registerAction->execute(
                RegisterMapper::fromRequest($request)
            );

            // Generate Access Token instantly for autologin
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->accessToken;
            $refreshToken = bin2hex(random_bytes(40));

            $user->load(['roles.permissions', 'permissions', 'patientProfile', 'branches']);

            return $this->successResponse([
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ], 'Registration completed successfully.', JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Public password login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginAction->execute(
            LoginMapper::fromRequest($request)
        );

        RateLimiter::clear($request->throttleKey());

        $user = $result['user'];
        $user->load(['roles.permissions', 'permissions', 'patientProfile', 'branches']);

        return $this->responseSuccess(
            [
                'access_token'  => $result['token'],
                'refresh_token' => $result['refresh_token'],
                'user'          => new UserResource($user),
            ],
            'Logged in successfully.'
        )->cookie(...$this->getCookieData($result['refresh_token']));
    }


    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        try {
            $http = new \GuzzleHttp\Client;
            $response = $http->post(config('app.url') . '/oauth/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $request->refresh_token,
                    'client_id' => config('passport.password_grant_client.id'),
                    'client_secret' => config('passport.password_grant_client.secret'),
                    'scope' => '',
                ]
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed',
                'data' => [
                    'access_token' => $body['access_token'],
                    'refresh_token' => $body['refresh_token'],
                    'expires_in' => $body['expires_in'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid refresh token',
            ], 401);
        }
    }

    /**
     * Handle public Google & Facebook authentication requests
     */
    public function socialLogin(SocialLoginRequest $request, SocialLoginAction $action): JsonResponse
    {
        try {
            $user = $action->execute(
                new SocialLoginDTO(
                    provider: $request->validated('provider'),
                    token: $request->validated('token')
                )
            );

            $token = $user->createToken('Personal Access Token')->accessToken;

            return $this->successResponse([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ], 'Social authentication completed successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('api') ?? $request->user() ?? Auth::user();
        $token = $user?->currentAccessToken() ?? $user?->token();

        if ($token !== null) {
            if (method_exists($token, 'revoke')) {
                $token->revoke();
            }
            if (method_exists($token, 'delete')) {
                $token->delete();
            }
        }

        return $this->successResponse(
            null,
            'Logged out successfully.',
            JsonResponse::HTTP_NO_CONTENT
        )->cookie(...$this->getCookieData(null, true));
    }


    public function profile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user('api') ?? $request->user() ?? Auth::user();

        $user->load(['roles.permissions', 'permissions', 'patientProfile', 'branches']);

        return $this->successResponse(
            new UserResource($user),
            'User profile retrieved successfully.'
        );
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user('api') ?? $request->user() ?? Auth::user();

        $this->passwordAction->execute($user, $request->validated('password'));

        return $this->successResponse(null, 'Password updated successfully.');
    }

    /**
     * Helper to generate cookie parameters
     */
    private function getCookieData(?string $token, bool $isExpired = false): array
    {
        $isLocal = config('app.env') === 'local';

        return [
            'refresh_token',
            $isExpired ? '' : $token,
            $isExpired ? -1 : self::REFRESH_TOKEN_EXPIRY,
            '/',
            $isLocal ? null : '.smilelab.com',
            !$isLocal, // secure
            true,       // httpOnly
            'Lax'       // sameSite
        ];
    }
}
