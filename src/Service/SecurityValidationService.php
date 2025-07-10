<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Psr\Log\LoggerInterface;

class SecurityValidationService
{
    private const CSRF_TOKEN_ID = 'product_form';
    private const MAX_REQUEST_SIZE = 50 * 1024 * 1024; 
    private const ALLOWED_ORIGINS = []; 
    private const XSS_DANGEROUS_TAGS = [
        '<script', '</script>', '<iframe', '</iframe>', '<object', '</object>',
        '<embed', '</embed>', '<form', '</form>', 'javascript:', 'vbscript:',
        'onload=', 'onerror=', 'onclick=', 'onmouseover=', 'onfocus='
    ];

    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private LoggerInterface $logger
    ) {}

    public function validateCsrfToken(Request $request): bool
    {
        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-TOKEN');
        if (!$token) {
            $this->logger->warning(" [" . __METHOD__ . "] Security: CSRF token missing", [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
            return false;
        }
        $csrfToken = new CsrfToken(self::CSRF_TOKEN_ID, $token);
        $isValid = $this->csrfTokenManager->isTokenValid($csrfToken);
        $this->logger->info(' [" . __METHOD__ . "] Security: CSRF token validation', [
            'ip' => $request->getClientIp(),
            'token_received' => substr($token, 0, 8) . '...',
            'user_agent' => $request->headers->get('User-Agent'),
            'is_valid' => $isValid
        ]);
        if (!$isValid) {
            $this->logger->warning(' [" . __METHOD__ . "] Security: CSRF token validation failed', [
                'ip' => $request->getClientIp(),
                'token_received' => substr($token, 0, 8) . '...',
                'user_agent' => $request->headers->get('User-Agent')
            ]);
        }
        return $isValid;
    }

    public function validateCsrfTokenAjax(Request $request): bool
    {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $request->request->get('_token');
        if (!$token) {
            $this->logger->warning('[" . __METHOD__ . "] Security: AJAX CSRF token missing', [
                'ip' => $request->getClientIp(),
                'route' => $request->get('_route')
            ]);
            return false;
        }
        $csrfToken = new CsrfToken(self::CSRF_TOKEN_ID, $token);
        $isValid = $this->csrfTokenManager->isTokenValid($csrfToken);
        if (!$isValid) {
            $this->logger->warning(' [" . __METHOD__ . "] Security: AJAX CSRF token validation failed', [
                'ip' => $request->getClientIp(),
                'route' => $request->get('_route')
            ]);
        }
        return $isValid;
    }
    
    public function validateRequestSecurity(Request $request): bool
    {
        $contentLength = $request->headers->get('Content-Length');
        if ($contentLength && $contentLength > self::MAX_REQUEST_SIZE) {
            $this->logger->warning(' [" . __METHOD__ . "] Security: Request too large', [
                'content_length' => $contentLength,
                'max_allowed' => self::MAX_REQUEST_SIZE,
                'ip' => $request->getClientIp()
            ]);
            return false;
        }

        if ($request->isMethod('POST')) {
            $contentType = $request->headers->get('Content-Type');
            $allowedTypes = [
                'application/x-www-form-urlencoded',
                'multipart/form-data',
                'application/json'
            ];
            $isValidContentType = false;
            foreach ($allowedTypes as $type) {
                if (strpos($contentType, $type) === 0) {
                    $isValidContentType = true;
                    break;
                }
            }
            if (!$isValidContentType) {
                $this->logger->warning(' [" . __METHOD__ . "] Security: Invalid Content-Type', [
                    'content_type' => $contentType,
                    'ip' => $request->getClientIp()
                ]);
                return false;
            }
        }
        if (!empty(self::ALLOWED_ORIGINS)) {
            $origin = $request->headers->get('Origin');
            
            if ($origin && !in_array($origin, self::ALLOWED_ORIGINS)) {
                $this->logger->warning(' [" . __METHOD__ . "] Security: Invalid Origin', [
                    'origin' => $origin,
                    'allowed_origins' => self::ALLOWED_ORIGINS,
                    'ip' => $request->getClientIp()
                ]);
                return false;
            }
        }
        $userAgent = $request->headers->get('User-Agent');
        if (empty($userAgent) || strlen($userAgent) < 10) {
            $this->logger->warning(' [" . __METHOD__ . "] Security: Suspicious User-Agent', [
                'user_agent' => $userAgent,
                'ip' => $request->getClientIp()
            ]);
            return false;
        }
        return true;
    }

    public function sanitizeInput(string $input): string
    {
        $input = $this->removeXssContent($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $input = trim($input);
        $input = preg_replace('/\s+/', ' ', $input);
        return $input;
    }

    private function removeXssContent(string $input): string
    {
        foreach (self::XSS_DANGEROUS_TAGS as $dangerousTag) {
            $input = str_ireplace($dangerousTag, '', $input);
        }
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/vbscript:/i', '', $input);
        $input = preg_replace('/data:/i', '', $input);
        $input = preg_replace('/on\w+\s*=/i', '', $input);
        return $input;
    }

    public function getCsrfTokenId(): string
    {
        return self::CSRF_TOKEN_ID;
    }
}