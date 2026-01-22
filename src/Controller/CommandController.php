<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class CommandController extends AbstractController
{
    #[Route('/cron/run/{command}', name: 'app_cron_run', methods: ['GET'])]
    public function runCommand(
        Request $request,
        KernelInterface $kernel,
        string $command
    ): JsonResponse {
        // Erlaubte Commands (Sicherheit: nur bestimmte Commands erlauben)
        $allowedCommands = [
            'app:auto-checkout' => 'app:auto-checkout',
        ];

        if (!isset($allowedCommands[$command])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Command nicht erlaubt',
                'command' => $command
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => $allowedCommands[$command],
            ]);

            $output = new BufferedOutput();
            $exitCode = $application->run($input, $output);

            $outputContent = $output->fetch();

            return new JsonResponse([
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'command' => $command,
                'output' => $outputContent,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ], $exitCode === 0 ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'command' => $command,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/cron/status', name: 'app_cron_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'available_commands' => [
                'app:auto-checkout' => '/cron/run/app:auto-checkout'
            ]
        ]);
    }
}
