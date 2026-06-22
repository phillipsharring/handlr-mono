<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class ExampleDetailPipe implements Pipe
{
    public function __construct(private Presenter $presenter) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        // Get the ID from query parameters (HTMX sends hx-vals as query params for GET requests)
        $id = $request->query('id') ?? 'unknown';

        // Generate different demo data based on the ID for variety
        $examples = [
            'demo-1' => [
                'icon' => '🎮',
                'name' => 'Demo Game Item',
                'type' => 'Demo',
                'description' => 'This is a demonstration item showing how dynamic routing works with the demo-1 ID.',
            ],
            'test-123' => [
                'icon' => '🧪',
                'name' => 'Test Item',
                'type' => 'Test',
                'description' => 'A test item used for validating route parameter extraction and API integration.',
            ],
            'custom' => [
                'icon' => '⚙️',
                'name' => 'Custom Configuration',
                'type' => 'Custom',
                'description' => 'A customizable example showing how you can build dynamic pages based on URL parameters.',
            ],
        ];

        // Check if we have a predefined example for this ID, otherwise use default
        if (isset($examples[$id])) {
            $data = $examples[$id];
        } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            // Looks like a UUID
            $data = [
                'icon' => '🔑',
                'name' => 'UUID Example',
                'type' => 'UUID',
                'description' => 'This example demonstrates route parameters with UUID identifiers, commonly used for database records.',
            ];
        } else {
            // Generic example
            $data = [
                'icon' => '📦',
                'name' => 'Generic Example',
                'type' => 'Generic',
                'description' => 'A generic example item. You can customize the API response based on any ID pattern you need.',
            ];
        }

        // Add the ID and timestamp to the response
        $data['id'] = $id;
        $data['timestamp'] = date('Y-m-d H:i:s');

        return $response->withJson(
            $this->presenter->withSingleData($data)->success()
        );
    }
}
