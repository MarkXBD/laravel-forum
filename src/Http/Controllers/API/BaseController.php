<?php

namespace Riari\Forum\Http\Controllers\API;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Riari\Forum\Forum;

abstract class BaseController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * @var mixed
     */
    protected $model;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var array
     */
    protected $rules;

    /**
     * @var string
     */
    protected $translationFile;

    /**
     * Create a new API controller instance.
     *
     * @param  object  $model
     * @param  Request  $request
     */
    public function __construct($model, Request $request)
    {
        $this->model = $model;

        if ($request->has('with')) {
            $this->model = $this->model->with($request->input('with'));
        }

        if ($request->has('append')) {
            $this->model = $this->model->append($request->input('append'));
        }

        $this->request = $request;
    }

    /**
     * GET: return an index of models.
     *
     * @return JsonResponse|Response
     */
    public function index()
    {
        return $this->response($this->model->paginate());
    }

    /**
     * GET: return a model by ID.
     *
     * @param  int  $id
     * @return JsonResponse|Response
     */
    public function show($id)
    {
        $model = $this->model->find($id);

        if (is_null($model) || !$model->exists) {
            return $this->notFoundResponse();
        }

        return $this->response($model);
    }

    /**
     * POST: create a new model.
     *
     * @return JsonResponse|Response
     */
    public function store()
    {
        $this->validate($this->rules['store']);

        $model = $this->model->create($this->request->all());

        return $this->response($model, $this->trans('created'), 201);
    }

    /**
     * PUT/PATCH: update a model by ID.
     *
     * @param  int  $id
     * @return JsonResponse|Response
     */
    public function update($id)
    {
        $model = $this->model->find($id);

        if (is_null($model) || !$model->exists) {
            return $this->notFoundResponse();
        }

        $this->authorize('edit', $model);

        $response = $this->doUpdate($model, $this->request);

        if ($response instanceof JsonResponse) {
            return $response;
        }

        return $this->response($response, $this->trans('updated'));
    }

    /**
     * DELETE: delete a model by ID.
     *
     * @param  int  $id
     * @return JsonResponse|Response
     */
    public function destroy($id)
    {
        $model = $this->model->find($id);

        if (is_null($model) || !$model->exists) {
            return $this->notFoundResponse();
        }

        $this->authorize('delete', $model);

        if ($this->request->has('force') && $this->request->input('force') == 1) {
            $model->forceDelete();
            $message = $this->trans('perma_deleted');
        } elseif (!$model->trashed()) {
            $model->delete();
            $message = $this->trans('deleted');
        }

        return $this->response($model, $message);
    }

    /**
     * PATCH: restore a model by ID.
     *
     * @param  int  $id
     * @return JsonResponse|Response
     */
    public function restore($id)
    {
        $model = $this->model->withTrashed()->find($id);

        if (is_null($model) || !$model->exists) {
            return $this->notFoundResponse();
        }

        $this->authorize('delete', $model);

        if ($model->trashed()) {
            $model->restore();
        }

        return $this->response($model, $this->trans('restored'));
    }

    /**
     * DELETE: bulk delete models.
     *
     * @return JsonResponse|Response
     */
    public function bulkDestroy()
    {
        $this->validate(['id' => 'required']);

        $collection = collect();
        foreach ($this->request->input('id') as $id) {
            $this->authorize('delete', $model);

            $model = $this->model->destroy($id);

            if (!is_null($model)) {
                $collection->push($model);
            }
        }

        return $this->response($collection, $this->trans('deleted', $collection->count()));
    }

    /**
     * PATCH: bulk restore models.
     *
     * @return JsonResponse|Response
     */
    public function bulkRestore()
    {
        $this->validate(['id' => 'required']);

        $collection = collect();
        foreach ($this->request->input('id') as $id) {
            $this->authorize('delete', $model);

            $model = $this->model->restore($id);

            if (!is_null($model)) {
                $collection->push($model);
            }
        }

        return $this->response($collection, $this->trans('restored', $collection->count()));
    }

    /**
     * Bulk update an attribute.
     *
     * @return JsonResponse|Response
     */
    protected function bulkUpdate()
    {
        $this->validate(['id' => 'required']);

        $input = $this->request->all();
        $this->request->replace($this->request->except('id'));
        $collection = collect();
        foreach ($input['id'] as $id) {
            $this->authorize('edit', $model);

            $model = $this->model->find($id);

            if (!is_null($model) && $model->exists) {
                $collection->push($this->doUpdate($model, $this->request));
            }
        }

        return $this->response($collection, $this->trans('updated', $collection->count()));
    }

    /**
     * Update a model.
     *
     * @param  Model  $model
     * @return mixed
     */
    protected function doUpdate($model)
    {
        if (is_null($model) || !$model->exists) {
            return $this->notFoundResponse();
        }

        $this->validate($this->rules['update']);

        $model->update($this->request->all());

        return $model;
    }

    /**
     * Create a generic response.
     *
     * @param  object  $data
     * @param  string  $message
     * @param  int  $code
     * @return JsonResponse|Response
     */
    protected function response($data, $message = "", $code = 200)
    {
        $message = empty($message) ? [] : compact('message');

        if ($this->request->ajax() || $this->request->wantsJson()) {
            return new JsonResponse($message + compact('data'), $code);
        }

        return new Response($message + compact('data'), $code);
    }

    /**
     * Create a 'not found' response.
     *
     * @return JsonResponse|Response
     */
    protected function notFoundResponse()
    {
        if ($this->request->ajax() || $this->request->wantsJson()) {
            return new JsonResponse(['error' => "The requested URL is invalid."], 404);
        }

        abort(404);
    }

    /**
     * Create the response for when a request fails validation.
     *
     * @param  Request  $request
     * @param  array  $errors
     * @return JsonResponse|Response
     */
    protected function buildFailedValidationResponse(Request $request, array $errors)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return new JsonResponse([
                'error'             => "The submitted data did not pass validation.",
                'validation_errors' => $errors
            ], 422);
        }

        abort(422);
    }

    /**
     * Fetch a translated string.
     *
     * @param  string  $key
     * @param  int  $count
     * @return string
     */
    protected function trans($key, $count = 1)
    {
        return Forum::trans($this->translationFile, $key, $count);
    }

    /**
     * Validate the given request with the given rules.
     *
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return void
     *
     * @throws \Illuminate\Http\Exception\HttpResponseException
     */
    public function validate(array $rules, array $messages = [], array $customAttributes = [])
    {
        $validator = $this->getValidationFactory()->make($this->request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            $this->throwValidationException($this->request, $validator);
        }
    }
}
