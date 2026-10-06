<?php

namespace App\Support\Tenancy;

use App\Models\Workspace;
use Closure;

/**
 * The workspace the current request or job works for.
 *
 * Web requests set it in SetCurrentWorkspace middleware. Queue jobs set it with
 * runAs() from the record they process, and restore the previous value after,
 * so a long-running worker never carries one customer's context into the next job.
 */
class CurrentWorkspace
{
    private ?int $id = null;

    private ?Workspace $model = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function get(): ?Workspace
    {
        if ($this->id === null) {
            return null;
        }

        return $this->model ??= Workspace::query()->find($this->id);
    }

    public function set(Workspace|int|null $workspace): void
    {
        $this->model = $workspace instanceof Workspace ? $workspace : null;
        $this->id = $workspace instanceof Workspace ? $workspace->id : $workspace;
    }

    public function clear(): void
    {
        $this->set(null);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Workspace|int|null $workspace, Closure $callback): mixed
    {
        [$previousId, $previousModel] = [$this->id, $this->model];
        $this->set($workspace);

        try {
            return $callback();
        } finally {
            [$this->id, $this->model] = [$previousId, $previousModel];
        }
    }
}
