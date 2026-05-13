<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the right {@see PaymentGateway} for a given payment
 * method. Config-driven so finance can swap providers without a
 * code change (e.g. switch `card` from `bog` to `tbc_pay`).
 *
 * Resolution order:
 *   1. If the method has an explicit gateway key in `payment.methods`,
 *      use it. ('card' → 'bog' on prod, 'card' → 'null' in CI.)
 *   2. Fall back to `payment.default`.
 *   3. If neither exists, throw — never silently match against the
 *      first-binding-by-accident.
 *
 * The resolved gateway is instantiated from `payment.gateways.<name>.class`
 * via the container so HTTP factories, loggers, and SDK clients are
 * dependency-injected.
 */
final class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $cache = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function forMethod(string $method): PaymentGateway
    {
        $name = $this->gatewayNameForMethod($method);

        return $this->driver($name);
    }

    public function driver(string $name): PaymentGateway
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $class = config("payment.gateways.$name.class");
        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Unknown payment gateway: {$name}");
        }

        /** @var PaymentGateway $gateway */
        $gateway = $this->container->make($class);

        return $this->cache[$name] = $gateway;
    }

    public function gatewayNameForMethod(string $method): string
    {
        $methods = (array) config('payment.methods', []);
        if (isset($methods[$method]) && is_string($methods[$method])) {
            return $methods[$method];
        }

        $default = config('payment.default');
        if (is_string($default) && $default !== '') {
            return $default;
        }

        throw new InvalidArgumentException("No gateway configured for method: {$method}");
    }
}
