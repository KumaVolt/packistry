<?php

declare(strict_types=1);

namespace App\Sources\Gitlab;

use App\Models\Repository;
use App\Sources\Branch;
use App\Sources\Client;
use App\Sources\Project;
use App\Sources\Tag;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitlabClient extends Client
{
    public function http(): PendingRequest
    {
        return Http::baseUrl($this->url)
            ->withHeader('Private-Token', $this->token);
    }

    /**
     * @throws ConnectionException
     */
    public function projects(): array
    {
        $response = $this->http()->get('/api/v4/projects');

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return array_map(fn (array $item): Project => new Project(
            id: $item['id'],
            fullName: $item['path_with_namespace'],
            name: $item['name'],
            url: $item['_links']['self'].'/repository',
            webUrl: $item['web_url'],
        ), $data);
    }

    /**
     * @throws ConnectionException
     */
    public function branches(Project $project): array
    {
        $response = $this->http()->get("$project->url/branches");

        $data = $response->json();

        if (is_null($data)) {
            new RuntimeException($response->getBody()->getContents());
        }

        return array_map(function (array $item) use ($project): Branch {
            $sha = $item['commit']['id'];

            return new Branch(
                id: (string) $project->id,
                name: $item['name'],
                url: $project->url,
                zipUrl: "$project->url/archive.zip?sha=$sha",
            );
        }, $data);
    }

    /**
     * @throws ConnectionException
     */
    public function tags(Project $project): array
    {
        $response = $this->http()->get("$project->url/tags");

        $data = $response->json();

        if (is_null($data)) {
            new RuntimeException($response->getBody()->getContents());
        }

        return array_map(function (array $item) use ($project): Tag {
            $sha = $item['commit']['id'];

            return new Tag(
                id: (string) $project->id,
                name: $item['name'],
                url: $project->url,
                zipUrl: "$project->url/archive.zip?sha=$sha",
            );
        }, $data);
    }

    /**
     * @throws ConnectionException
     */
    public function createWebhook(Repository $repository, Project $project): void
    {
        $this->http()->post("$project->url/hooks", [
            'url' => url($repository->url('/incoming/gitlab')),
            'name' => 'conductor sync',
            'token' => config('services.gitea.webhook.secret'),
            'content_type' => 'json',
            'tag_push_events' => true,
            'branch_push_events' => true,
        ]);
    }
}
