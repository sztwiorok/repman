<?php

declare(strict_types=1);

namespace Buddy\Repman\Service\GitHubApi;

use Buddy\Repman\Service\GitHubApi;
use Github\Api\Organization;
use Github\Client;

final class KnpGitHubApi implements GitHubApi
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function primaryEmail(string $accessToken): string
    {
        $this->client->authenticate($accessToken, null, Client::AUTH_JWT);
        foreach ($this->client->currentUser()->emails()->all() as $email) {
            if ($email['primary'] === true) {
                return $email['email'];
            }
        }

        throw new \RuntimeException('Primary e-mail not found.');
    }

    /**
     * @return string[]
     */
    public function repositories(string $accessToken): array
    {
        $this->client->authenticate($accessToken, null, Client::AUTH_JWT);
        $memberships = $this->memberships();
        $privateRepos = $this->privateRepos();
        $result = array_map(fn ($repo) => $repo['full_name'], $privateRepos);

        foreach ($memberships as $membership) {
            $organizationLogin = $membership['organization']['login'];
            $repos = $this->organizationRepos($organizationLogin);

            foreach ($repos as $repo) {
                $result[] = $repo['full_name'];
            }
        }

        return $result;
    }

    /**
     * @codeCoverageIgnore
     */
    public function addHook(string $accessToken, string $repo, string $url): void
    {
        list($owner, $repo) = explode('/', $repo);
        $this->client->authenticate($accessToken, null, Client::AUTH_JWT);
        $this->client->repositories()->hooks()->create($owner, $repo, [
            'name' => 'web',
            'config' => [
                'url' => $url,
                'content_type' => 'json',
            ],
        ]);
    }

    /**
     * @return array[]
     */
    private function privateRepos(): array
    {
        return $this->client->currentUser()->repositories();
    }

    /**
     * @return array[]
     */
    private function organizationRepos(string $organization): array
    {
        /** @var Organization */
        $org = $this->client->api('organization');

        return $org->repositories($organization);
    }

    /**
     * @return array[]
     */
    private function memberships(): array
    {
        return $this->client->currentUser()->memberships()->all();
    }
}
