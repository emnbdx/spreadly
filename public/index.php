<?php

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\AdminController;
use App\Controllers\CampaignController;
use App\Controllers\OnboardingController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\CampaignMiddleware;
use App\Middleware\SessionMiddleware;
use App\Models\User;
use App\Models\CampaignUser;
use App\Models\Love;
use App\Models\Campaign;
use App\Models\CampaignAdmin;
use App\Services\EmailService;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

$container = new Container();

$container->set('config', $config);

$container->set(PDO::class, function () use ($config) {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['database']['host'],
        $config['database']['port'],
        $config['database']['name']
    );

    return new PDO(
        $dsn,
        $config['database']['user'],
        $config['database']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
});

$container->set(User::class, function (Container $c) use ($config) {
    return new User($c->get(PDO::class), $config['database']['prefix']);
});

$container->set(Love::class, function (Container $c) use ($config) {
    return new Love($c->get(PDO::class), $config['database']['prefix']);
});

$container->set(Campaign::class, function (Container $c) use ($config) {
    return new Campaign($c->get(PDO::class), $config['database']['prefix']);
});

$container->set(CampaignUser::class, function (Container $c) use ($config) {
    return new CampaignUser($c->get(PDO::class), $config['database']['prefix']);
});

$container->set(CampaignAdmin::class, function (Container $c) use ($config) {
    return new CampaignAdmin($c->get(PDO::class), $config['database']['prefix']);
});

$container->set(EmailService::class, function () use ($config) {
    return new EmailService($config['email']);
});

$container->set(Twig::class, function () {
    return Twig::create(__DIR__ . '/../templates');
});

$container->set(AuthController::class, function (Container $c) {
    return new AuthController(
        $c->get(User::class),
        $c->get(CampaignUser::class),
        $c->get(EmailService::class),
        $c->get(Twig::class)
    );
});

$container->set(HomeController::class, function (Container $c) use ($config) {
    return new HomeController(
        $c->get(User::class),
        $c->get(CampaignUser::class),
        $c->get(Love::class),
        $c->get(Campaign::class),
        $c->get(CampaignAdmin::class),
        $c->get(Twig::class),
        $config['app']
    );
});

$container->set(AdminController::class, function (Container $c) {
    return new AdminController(
        $c->get(User::class),
        $c->get(CampaignUser::class),
        $c->get(Love::class),
        $c->get(Campaign::class),
        $c->get(CampaignAdmin::class),
        $c->get(EmailService::class),
        $c->get(Twig::class)
    );
});

$container->set(CampaignController::class, function (Container $c) {
    return new CampaignController(
        $c->get(Campaign::class),
        $c->get(User::class),
        $c->get(CampaignUser::class),
        $c->get(Love::class),
        $c->get(CampaignAdmin::class),
        $c->get(Twig::class)
    );
});

$container->set(OnboardingController::class, function (Container $c) {
    return new OnboardingController(
        $c->get(Campaign::class),
        $c->get(User::class),
        $c->get(CampaignUser::class),
        $c->get(CampaignAdmin::class),
        $c->get(EmailService::class),
        $c->get(Twig::class)
    );
});

AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware in reverse order (last added = first executed)
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));
$app->add(SessionMiddleware::class);

$app->get('/login', [AuthController::class, 'showLogin']);
$app->get('/create-spreadly', [OnboardingController::class, 'showCreate']);
$app->post('/auth/request', [AuthController::class, 'requestCode']);
$app->post('/auth/verify', [AuthController::class, 'verifyCode']);
$app->post('/create-spreadly', [OnboardingController::class, 'create']);
$app->post('/auth/logout', [AuthController::class, 'logout']);

// Routes pour la gestion des Spreadly (nécessitent seulement l'authentification)
$app->group('', function ($group) {
    $group->get('/campaigns', [CampaignController::class, 'index']);
    $group->get('/campaigns/create', [CampaignController::class, 'create']);
    $group->post('/campaigns/create', [CampaignController::class, 'store']);
    $group->get('/campaigns/edit/{id:[0-9]+}', [CampaignController::class, 'edit']);
    $group->post('/campaigns/edit/{id:[0-9]+}', [CampaignController::class, 'update']);
    $group->post('/campaigns/delete/{id:[0-9]+}', [CampaignController::class, 'delete']);
    $group->get('/campaigns/switch/{id:[0-9]+}', [CampaignController::class, 'switchCampaign']);
})->add(AuthMiddleware::class);

// Route publique pour accès aux Spreadly (APRÈS les routes statiques)
$app->get('/campaigns/{slug}', [CampaignController::class, 'publicAccess']);

// Routes qui nécessitent une Spreadly sélectionnée
$app->group('', function ($group) {
    $group->get('/', [HomeController::class, 'index']);
    $group->post('/send', [HomeController::class, 'sendLove']);
    $group->post('/message/delete/{id:[0-9]+}', [HomeController::class, 'deleteMessage']);
})->add(CampaignMiddleware::class)->add(AuthMiddleware::class);

// Routes admin qui nécessitent une Spreadly sélectionnée
$app->group('', function ($group) {
    $group->get('/admin', [AdminController::class, 'index']);
    $group->post('/admin/create', [AdminController::class, 'createUser']);
    $group->get('/admin/edit/{id:[0-9]+}', [AdminController::class, 'editUser']);
    $group->post('/admin/edit/{id:[0-9]+}', [AdminController::class, 'updateUser']);
    $group->post('/admin/delete/{id:[0-9]+}', [AdminController::class, 'deleteUser']);
    $group->post('/admin/import', [AdminController::class, 'importUsers']);
    $group->post('/admin/send', [AdminController::class, 'sendEmails']);
})->add(CampaignMiddleware::class)->add(function ($request, $handler) use ($container) {
    $adminMiddleware = new AdminMiddleware($container->get(CampaignAdmin::class));
    return $adminMiddleware->process($request, $handler);
});

$app->run();
