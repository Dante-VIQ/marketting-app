<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Passkeys\Contracts\PasskeyUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PasskeySupportTest extends TestCase
{
    public function test_user_implements_the_passkey_contract_the_package_requires(): void
    {
        $this->assertTrue(is_subclass_of(User::class, PasskeyUser::class));
        $this->assertTrue(method_exists(User::class, 'passkeys'));
        $this->assertTrue(method_exists(User::class, 'getPasskeyUserHandle'));
    }

    #[DataProvider('views')]
    public function test_passkey_views_compile_to_valid_php(string $view): void
    {
        $dir = sys_get_temp_dir() . '/bladecheck-' . uniqid();
        mkdir($dir);
        $c = new BladeCompiler(new Filesystem(), $dir);
        $php = $c->compileString(file_get_contents(__DIR__ . '/../../resources/views/' . $view));
        $f = $dir . '/out.php';
        file_put_contents($f, $php);
        exec('php -l ' . escapeshellarg($f) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public static function views(): array
    {
        return [['auth/login.blade.php'], ['auth/confirm-password.blade.php'], ['account/security.blade.php']];
    }

    public function test_menu_links_to_the_security_page(): void
    {
        $this->assertStringContainsString("route('account.security')", file_get_contents(__DIR__ . '/../../resources/views/layouts/app.blade.php'));
    }

    public function test_passkeys_are_not_enabled_without_the_client_script(): void
    {
        $js = file_get_contents(__DIR__ . '/../../resources/js/app.js');
        $this->assertStringContainsString("@laravel/passkeys", $js);
        $this->assertStringContainsString('window.Passkeys', $js);
        $this->assertStringContainsString('@laravel/passkeys', file_get_contents(__DIR__ . '/../../package.json'));
    }

    public function test_security_page_asks_for_the_password_and_login_goes_to_the_dashboard(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/web.php');
        $this->assertMatchesRegularExpression("/'password\.confirm'\]\)->get\('\/account\/security'/", $routes);
        $this->assertStringContainsString("'redirect' => '/dashboard'", file_get_contents(__DIR__ . '/../../config/passkeys.php'));
        $this->assertStringContainsString('confirmPasswordView', file_get_contents(__DIR__ . '/../../app/Providers/FortifyServiceProvider.php'));
    }
}
