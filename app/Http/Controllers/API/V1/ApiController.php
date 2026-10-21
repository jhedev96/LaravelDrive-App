<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected int $statusCode = 200;

    const CODE_WRONG_ARGS = 'GEN-FUBARGS';
    const CODE_NOT_FOUND = 'GEN-LIKETHEWIND';
    const CODE_INTERNAL_ERROR = 'GEN-AAAGGH';
    const CODE_UNAUTHORIZED = 'GEN-MAYBGTFO';
    const CODE_FORBIDDEN = 'GEN-GTFO';
    const CODE_INVALID_MIME_TYPE = 'GEN-UMWUT';

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Respond a no content response.
     */
    public function noContent()
    {
        return response()->json(null, 204);
    }

    /**
     * Generic success holder used by a few legacy controllers.
     */
    public function success($data = [])
    {
        return $this->setStatusCode(200)
            ->respondWithArray(array_merge(['success' => true], $data));
    }

    public function respondWithItem($item)
    {
        return new JsonResource($item);
    }

    public function respondWithCollection($collection)
    {
        return JsonResource::collection($collection);
    }

    public function respondWithPaginator($collection)
    {
        return JsonResource::collection($collection);
    }

    public function respondWithArray(array $array, array $headers = [])
    {
        return response()->json($array, $this->statusCode, $headers);
    }

    public function respondWithMessage($message)
    {
        return $this->setStatusCode(200)
            ->respondWithArray([
                'message' => $message,
            ]);
    }

    protected function respondWithError($message, $errorCode, $errors = [])
    {
        if (app()->isProduction() && $this->statusCode === 200) {
            trigger_error(
                'You better have a really good reason for erroring on a 200...',
                E_USER_WARNING
            );
        }

        return $this->respondWithArray([
            'errors' => $errors,
            'code' => $errorCode,
            'message' => $message,
        ]);
    }

    public function errorForbidden($message = 'Forbidden', $errors = [])
    {
        return $this->setStatusCode(403)
            ->respondWithError($message, self::CODE_FORBIDDEN, $errors);
    }

    public function errorInternalError($message = 'Internal Error', $errors = [])
    {
        return $this->setStatusCode(500)
            ->respondWithError($message, self::CODE_INTERNAL_ERROR, $errors);
    }

    public function errorNotFound($message = 'Resource Not Found', $errors = [])
    {
        return $this->setStatusCode(404)
            ->respondWithError($message, self::CODE_NOT_FOUND, $errors);
    }

    public function errorUnauthorized($message = 'Unauthorized', $errors = [])
    {
        return $this->setStatusCode(401)
            ->respondWithError($message, self::CODE_UNAUTHORIZED, $errors);
    }

    public function errorWrongArgs($message = 'Wrong Arguments', $errors = [])
    {
        return $this->setStatusCode(400)
            ->respondWithError($message, self::CODE_WRONG_ARGS, $errors);
    }
}
