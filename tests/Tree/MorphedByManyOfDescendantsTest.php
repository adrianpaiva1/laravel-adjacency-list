<?php

namespace Staudenmeir\LaravelAdjacencyList\Tests\Tree;

use Illuminate\Database\Eloquent\SoftDeletingScope;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\BelongsToManyOfDescendants;
use Staudenmeir\LaravelAdjacencyList\Tests\Scopes\DepthScope;
use Staudenmeir\LaravelAdjacencyList\Tests\Tree\Models\User;
use Staudenmeir\LaravelAdjacencyList\Tests\Tree\Models\Video;

class MorphedByManyOfDescendantsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection === 'singlestore') {
            $this->markTestSkipped();
        }
    }

    public function testLazyLoading(): void
    {
        $videos = User::find(2)->videos;

        $this->assertEquals([53, 83], $videos->pluck('id')->all());
    }

    public function testLazyLoadingAndSelf(): void
    {
        $videos = User::find(2)->videosAndSelf;

        $this->assertEquals([23, 53, 83], $videos->pluck('id')->all());
    }

    public function testEagerLoading(): void
    {
        $users = User::with([
            'videos' => fn (BelongsToManyOfDescendants $query) => $query->orderBy('id'),
        ])->orderBy('id')->get();

        $this->assertEquals([23, 33, 43, 53, 63, 73, 83], $users[0]->videos->pluck('id')->all());
        $this->assertEquals([53, 83], $users[1]->videos->pluck('id')->all());
        $this->assertEquals([], $users[8]->videos->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videos->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videos[0]);
    }

    public function testEagerLoadingAndSelf(): void
    {
        $users = User::with([
            'videosAndSelf' => fn (BelongsToManyOfDescendants $query) => $query->orderBy('id'),
        ])->orderBy('id')->get();

        $this->assertEquals([13, 23, 33, 43, 53, 63, 73, 83], $users[0]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([23, 53, 83], $users[1]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([], $users[8]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videosAndSelf->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videosAndSelf[0]);
    }

    public function testLazyEagerLoading(): void
    {
        $users = User::orderBy('id')->get()->load([
            'videos' => fn (BelongsToManyOfDescendants $query) => $query->orderBy('id'),
        ]);

        $this->assertEquals([23, 33, 43, 53, 63, 73, 83], $users[0]->videos->pluck('id')->all());
        $this->assertEquals([53, 83], $users[1]->videos->pluck('id')->all());
        $this->assertEquals([], $users[8]->videos->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videos->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videos[0]);
    }

    public function testLazyEagerLoadingAndSelf(): void
    {
        $users = User::orderBy('id')->get()->load([
            'videosAndSelf' => fn (BelongsToManyOfDescendants $query) => $query->orderBy('id'),
        ]);

        $this->assertEquals([13, 23, 33, 43, 53, 63, 73, 83], $users[0]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([23, 53, 83], $users[1]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([], $users[8]->videosAndSelf->pluck('id')->all());
        $this->assertEquals([103, 113], $users[9]->videosAndSelf->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->videosAndSelf[0]);
    }

    public function testExistenceQuery(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv', 'firebird'])) {
            $this->markTestSkipped();
        }

        $users = User::find(8)->ancestors()->has('videos', '>', 1)->get();

        $this->assertEquals([2, 1], $users->pluck('id')->all());
    }

    public function testExistenceQueryAndSelf(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv', 'firebird'])) {
            $this->markTestSkipped();
        }

        $users = User::find(8)->ancestors()->has('videosAndSelf', '>', 2)->get();

        $this->assertEquals([2, 1], $users->pluck('id')->all());
    }

    public function testExistenceQueryForSelfRelation(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv', 'firebird'])) {
            $this->markTestSkipped();
        }

        $users = User::has('videos', '>', 1)->get();

        $this->assertEquals([1, 2, 11], $users->pluck('id')->all());
    }

    public function testExistenceQueryForSelfRelationAndSelf(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv'])) {
            $this->markTestSkipped();
        }

        $users = User::has('videosAndSelf', '>', 2)->get();

        $this->assertEquals([1, 2], $users->pluck('id')->all());
    }

    public function testDelete(): void
    {
        if (in_array($this->connection, ['mariadb', 'firebird'])) {
            $this->markTestSkipped();
        }

        $affected = User::find(1)->videos()->delete();

        $this->assertEquals(7, $affected);
        $this->assertNotNull(Video::withTrashed()->find(83)->deleted_at);
        $this->assertNull(Video::find(13)->deleted_at);
    }

    public function testDeleteAndSelf(): void
    {
        if (in_array($this->connection, ['mariadb', 'firebird'])) {
            $this->markTestSkipped();
        }

        $affected = User::find(1)->videosAndSelf()->delete();

        $this->assertEquals(8, $affected);
        $this->assertNotNull(Video::withTrashed()->find(83)->deleted_at);
        $this->assertNotNull(Video::withTrashed()->find(13)->deleted_at);
    }

    public function testWithTrashedDescendants(): void
    {
        $videos = User::find(4)->videos()->withTrashedDescendants()->get();

        $this->assertEquals([73, 93], $videos->pluck('id')->all());
    }

    public function testWithIntermediateScope(): void
    {
        $videos = User::find(2)->videos()->withIntermediateScope('depth', new DepthScope())->get();

        $this->assertEquals([53], $videos->pluck('id')->all());
    }

    public function testWithoutIntermediateScope(): void
    {
        $videos = User::find(2)->videos()
            ->withIntermediateScope('depth', new DepthScope())
            ->withoutIntermediateScope('depth')
            ->get();

        $this->assertEquals([53, 83], $videos->pluck('id')->all());
    }

    public function testWithoutIntermediateScopeWithObject(): void
    {
        $videos = User::find(4)->videos()->withoutIntermediateScope(new SoftDeletingScope())->get();

        $this->assertEquals([73, 93], $videos->pluck('id')->all());
    }

    public function testWithoutIntermediateScopes(): void
    {
        $videos = User::find(2)->videos()
            ->withIntermediateScope('depth', new DepthScope())
            ->withoutIntermediateScopes()
            ->get();

        $this->assertEquals([53, 83], $videos->pluck('id')->all());
    }

    public function testIntermediateScopes(): void
    {
        $relationship = User::find(2)->videos()->withIntermediateScope('depth', new DepthScope());

        $this->assertArrayHasKey('depth', $relationship->intermediateScopes());
    }

    public function testRemovedIntermediateScopes(): void
    {
        $relationship = User::find(2)->videos()
            ->withIntermediateScope('depth', new DepthScope())
            ->withoutIntermediateScope('depth');

        $this->assertSame(['depth'], $relationship->removedIntermediateScopes());
    }
}
