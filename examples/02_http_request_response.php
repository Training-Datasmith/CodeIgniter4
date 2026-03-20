<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 — HTTP request / response example.
 *
 * Demonstrates reading from the incoming request (headers, query string,
 * body, uploaded files) and building a structured JSON response using
 * CodeIgniter's mutable HTTP layer.
 *
 * This is a controller action snippet suitable for inclusion inside a
 * full CodeIgniter 4 application.
 */

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ApiController shows request parsing and JSON response construction.
 */
class ApiController extends Controller
{
    /**
     * GET /api/info
     *
     * Returns request metadata as JSON.
     */
    public function info(): ResponseInterface
    {
        /** @var IncomingRequest $request */
        $request = service('request');

        $data = [
            'method'      => $request->getMethod(),
            'uri'         => (string) $request->getUri(),
            'is_ajax'     => $request->isAJAX(),
            'locale'      => $request->getLocale(),
            'user_agent'  => $request->getUserAgent()->getAgentString(),
            'accept'      => $request->getHeaderLine('Accept'),
            'query'       => $request->getGetPost(),   // merged GET + POST
        ];

        return $this->response
            ->setStatusCode(Response::HTTP_OK)
            ->setContentType('application/json')
            ->setBody(json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * POST /api/upload
     *
     * Accepts a single file upload and returns the stored filename.
     */
    public function upload(): ResponseInterface
    {
        /** @var IncomingRequest $request */
        $request = service('request');

        $file = $request->getFile('document');

        if ($file === null || !$file->isValid()) {
            return $this->response
                ->setStatusCode(Response::HTTP_BAD_REQUEST)
                ->setJSON(['error' => 'No valid file provided.']);
        }

        if ($file->hasMoved()) {
            return $this->response
                ->setStatusCode(Response::HTTP_BAD_REQUEST)
                ->setJSON(['error' => 'File already moved.']);
        }

        // Store the file; getRandomName() prevents directory traversal.
        $newName = $file->getRandomName();
        $file->move(WRITEPATH . 'uploads', $newName);

        return $this->response
            ->setStatusCode(Response::HTTP_CREATED)
            ->setJSON([
                'original' => $file->getClientName(),
                'stored'   => $newName,
                'size'     => $file->getSizeByUnit('kb') . ' KB',
                'mime'     => $file->getMimeType(),
            ]);
    }

    /**
     * GET /api/redirect-example
     *
     * Demonstrates a redirect response with a flash message.
     */
    public function redirectExample(): ResponseInterface
    {
        return redirect()
            ->to('/dashboard')
            ->with('success', 'You have been redirected!');
    }
}
