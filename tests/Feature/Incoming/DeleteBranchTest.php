<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\Version;

it('deletes branch', function (Repository $repository, ...$args): void {
    /** @var Version $version */
    $version = Version::query()->latest('id')->first();

    webhook($repository, ...$args)
        ->assertOk()
        ->assertExactJson([
            'id' => $version->id,
            'package_id' => $version->package->id,
            'name' => $version->name,
            'metadata' => $version->metadata,
            'shasum' => $version->shasum,
            'created_at' => $version->created_at,
            'updated_at' => $version->updated_at,
        ]);

    expect(Version::query()->count())->toBe(0);
})
    ->with(rootAndSubRepositoryWithPackageFromZip(
        name: 'vendor/test',
        version: 'dev-feature-something',
        zip: __DIR__.'/../../Fixtures/gitea-jamie-test.zip',
        subDirectory: 'test/'
    ))
    ->with(providerDeleteEvents(
        refType: 'heads',
        ref: 'feature-something'
    ));
