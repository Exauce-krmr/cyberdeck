<?php

// Faux Leaf et faux Blade, juste assez pour exécuter routes et contrôleurs sans Composer.
// Ils reprennent l'API de Leaf utilisée par app/Core/Http.php et app/Core/View.php (README leafs/leaf et leafs/blade 5.0).

namespace Leaf {
    if (!class_exists(Blade::class)) {
    class Blade
    {
        public function __construct(public string $views, public string $cache)
        {
        }

        /** Renvoie le nom de la vue et les variables reçues : suffisant pour vérifier ce que les contrôleurs fournissent. */
        public function render(string $name, array $data = []): string
        {
            return '[view:' . $name . '|' . implode(',', array_keys($data)) . ']';
        }
    }
    }
}

namespace {
    class FakeApp
    {
        /** @var array<string, callable> */
        public array $routes = [];

        public function get(string $path, callable $handler): void
        {
            $this->routes["GET $path"] = $handler;
        }

        public function post(string $path, callable $handler): void
        {
            $this->routes["POST $path"] = $handler;
        }

        /** @return array{type: string, body: mixed, status: int} */
        public function dispatch(string $method, string $path): array
        {
            FakeResponse::$last = ['type' => 'none', 'body' => null, 'status' => 200];
            ($this->routes["$method $path"] ?? fn () => FakeResponse::$last = ['type' => 'none404', 'body' => null, 'status' => 404])();

            return FakeResponse::$last;
        }
    }

    class FakeResponse
    {
        public static array $last = [];

        public function markup(string $data, int $code = 200): void
        {
            self::$last = ['type' => 'html', 'body' => $data, 'status' => $code];
        }

        public function json(array $data, int $code = 200): void
        {
            self::$last = ['type' => 'json', 'body' => $data, 'status' => $code];
        }

        public function redirect(string $url): void
        {
            self::$last = ['type' => 'redirect', 'body' => $url, 'status' => 302];
        }
    }

    function app(): FakeApp
    {
        static $app = new FakeApp();

        return $app;
    }

    function response(): FakeResponse
    {
        static $response = new FakeResponse();

        return $response;
    }
}
