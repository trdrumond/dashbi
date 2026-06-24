<?php

/**
 * Controller base - validação e resposta padrão
 * PHP 7.3+
 */

require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';

abstract class Controller
{
    /** @var Request */
    protected $request;

    /** @var array */
    protected $params = [];

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Retorna parâmetro da rota por nome
     */
    protected function param(string $name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Valida campos obrigatórios
     * @return array lista de erros (vazia se válido)
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $parts = explode('|', $rule);
            foreach ($parts as $r) {
                if ($r === 'required' && ($value === null || $value === '')) {
                    $errors[$field] = "Campo {$field} é obrigatório.";
                    break;
                }
                if (strpos($r, 'max:') === 0) {
                    $max = (int) substr($r, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $errors[$field] = "Campo {$field} deve ter no máximo {$max} caracteres.";
                        break;
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * Resposta JSON de sucesso
     */
    protected function success($data = [], string $message = 'OK', int $status = 200): Response
    {
        return Response::jsonSuccess($data, $message, $status);
    }

    /**
     * Resposta JSON de erro
     */
    protected function error(string $message, int $status = 400, $errors = null): Response
    {
        return Response::jsonError($message, $status, $errors);
    }
}
