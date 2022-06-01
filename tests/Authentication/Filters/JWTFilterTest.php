<?php

namespace Tests\Authentication\Filters;

use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Authentication\TokenGenerator\JWTGenerator;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Filters\JWTAuth;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class JWTFilterTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    protected $namespace;

    protected function setUp(): void
    {
        Services::reset(true);

        parent::setUp();

        $_SESSION = [];

        // Register our filter
        $filterConfig                     = \config('Filters');
        $filterConfig->aliases['jwtAuth'] = JWTAuth::class;
        Factories::injectMock('filters', 'filters', $filterConfig);

        // Add a test route that we can visit to trigger.
        $routes = \service('routes');
        $routes->group('/', ['filter' => 'jwtAuth'], static function ($routes) {
            $routes->get('protected-route', static function () {
                echo 'Protected';
            });
        });
        $routes->get('open-route', static function () {
            echo 'Open';
        });
        $routes->get('login', 'AuthController::login', ['as' => 'login']);
        Services::injectMock('routes', $routes);
    }

    public function testFilterNotAuthorized()
    {
        $result = $this->call('get', 'protected-route');

        $result->assertStatus(401);

        $result = $this->get('open-route');

        $result->assertStatus(200);
        $result->assertSee('Open');
    }

    public function testFilterSuccess()
    {
        /** @var User $user */
        $user = \fake(UserModel::class);

        $generator = new JWTGenerator();
        $token     = $generator->generateAccessToken($user);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('protected-route');

        $result->assertStatus(200);
        $result->assertSee('Protected');

        $this->assertSame($user->id, \auth('jwt')->id());
        $this->assertSame($user->id, \auth('jwt')->user()->id);
    }
}
