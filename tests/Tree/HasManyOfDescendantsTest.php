<?php

namespace Staudenmeir\LaravelAdjacencyList\Tests\Tree;

use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\HasManyOfDescendants;
use Staudenmeir\LaravelAdjacencyList\Tests\Scopes\DepthScope;
use Staudenmeir\LaravelAdjacencyList\Tests\Tree\Models\Post;
use Staudenmeir\LaravelAdjacencyList\Tests\Tree\Models\Role;
use Staudenmeir\LaravelAdjacencyList\Tests\Tree\Models\User;

class HasManyOfDescendantsTest extends TestCase
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
        $posts = User::find(2)->posts;

        $this->assertEquals([50, 80], $posts->pluck('id')->all());
    }

    public function testLazyLoadingAndSelf(): void
    {
        $posts = User::find(2)->postsAndSelf;

        $this->assertEquals([20, 50, 80], $posts->pluck('id')->all());
    }

    public function testLazyLoadingWithoutParentKey(): void
    {
        $posts = (new User())->posts()->get();

        $this->assertEmpty($posts);
    }

    public function testEagerLoading(): void
    {
        $users = User::with([
            'posts' => fn (HasManyOfDescendants $query) => $query->orderBy('id'),
        ])->orderBy('id')->get();

        $this->assertEquals([20, 30, 40, 50, 60, 70, 80], $users[0]->posts->pluck('id')->all());
        $this->assertEquals([50, 80], $users[1]->posts->pluck('id')->all());
        $this->assertEquals([], $users[8]->posts->pluck('id')->all());
        $this->assertEquals([100, 110], $users[9]->posts->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->posts[0]);
    }

    public function testEagerLoadingAndSelf(): void
    {
        $users = User::with([
            'postsAndSelf' => fn (HasManyOfDescendants $query) => $query->orderBy('id'),
        ])->orderBy('id')->get();

        $this->assertEquals([10, 20, 30, 40, 50, 60, 70, 80], $users[0]->postsAndSelf->pluck('id')->all());
        $this->assertEquals([20, 50, 80], $users[1]->postsAndSelf->pluck('id')->all());
        $this->assertEquals([], $users[8]->postsAndSelf->pluck('id')->all());
        $this->assertEquals([100, 110], $users[9]->postsAndSelf->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->postsAndSelf[0]);
    }

    public function testNestedEagerLoadingWithEmptyResults(): void
    {
        Post::query()->delete();

        $roles = Role::with('users.posts')->get();

        $this->assertEmpty($roles[0]->users[0]->posts);
    }

    public function testLazyEagerLoading(): void
    {
        $users = User::orderBy('id')->get()->load([
            'posts' => fn (HasManyOfDescendants $query) => $query->orderBy('id'),
        ]);

        $this->assertEquals([20, 30, 40, 50, 60, 70, 80], $users[0]->posts->pluck('id')->all());
        $this->assertEquals([50, 80], $users[1]->posts->pluck('id')->all());
        $this->assertEquals([], $users[8]->posts->pluck('id')->all());
        $this->assertEquals([100, 110], $users[9]->posts->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->posts[0]);
    }

    public function testLazyEagerLoadingAndSelf(): void
    {
        $users = User::orderBy('id')->get()->load([
            'postsAndSelf' => fn (HasManyOfDescendants $query) => $query->orderBy('id'),
        ]);

        $this->assertEquals([10, 20, 30, 40, 50, 60, 70, 80], $users[0]->postsAndSelf->pluck('id')->all());
        $this->assertEquals([20, 50, 80], $users[1]->postsAndSelf->pluck('id')->all());
        $this->assertEquals([], $users[8]->postsAndSelf->pluck('id')->all());
        $this->assertEquals([100, 110], $users[9]->postsAndSelf->pluck('id')->all());
        $this->assertArrayNotHasKey('laravel_paths', $users[0]->postsAndSelf[0]);
    }

    public function testExistenceQuery(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv', 'firebird'])) {
            $this->markTestSkipped();
        }

        $users = User::find(8)->ancestors()->has('posts', '>', 1)->get();

        $this->assertEquals([2, 1], $users->pluck('id')->all());
    }

    public function testExistenceQueryAndSelf(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv', 'firebird'])) {
            $this->markTestSkipped();
        }

        $users = User::find(8)->ancestors()->has('postsAndSelf', '>', 2)->get();

        $this->assertEquals([2, 1], $users->pluck('id')->all());
    }

    public function testExistenceQueryForSelfRelation(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv', 'firebird'])) {
            $this->markTestSkipped();
        }

        $users = User::has('posts', '>', 1)->get();

        $this->assertEquals([1, 2, 11], $users->pluck('id')->all());
    }

    public function testExistenceQueryForSelfRelationAndSelf(): void
    {
        if (in_array($this->connection, ['mariadb', 'sqlsrv', 'firebird'])) {
            $this->markTestSkipped();
        }

        $users = User::has('postsAndSelf', '>', 2)->get();

        $this->assertEquals([1, 2], $users->pluck('id')->all());
    }

    public function testUpdate(): void
    {
        if (in_array($this->connection, ['mariadb', 'firebird'])) {
            $this->markTestSkipped();
        }

        $affected = User::find(1)->posts()->update(['user_id' => 11]);

        $this->assertEquals(7, $affected);
        $this->assertEquals(11, Post::find(80)->user_id);
        $this->assertEquals(1, Post::find(10)->user_id);
    }

    public function testUpdateAndSelf(): void
    {
        if (in_array($this->connection, ['mariadb', 'firebird'])) {
            $this->markTestSkipped();
        }

        $affected = User::find(1)->postsAndSelf()->update(['user_id' => 11]);

        $this->assertEquals(8, $affected);
        $this->assertEquals(11, Post::find(80)->user_id);
        $this->assertEquals(11, Post::find(10)->user_id);
    }

    public function testWithTrashedDescendants(): void
    {
        $posts = User::find(4)->posts()->withTrashedDescendants()->get();

        $this->assertEquals([70, 90], $posts->pluck('id')->all());
    }

    public function testWithIntermediateScope(): void
    {
        $posts = User::find(2)->posts()->withIntermediateScope('depth', new DepthScope())->get();

        $this->assertEquals([50], $posts->pluck('id')->all());
    }

    public function testWithoutIntermediateScope(): void
    {
        $posts = User::find(2)->posts()
            ->withIntermediateScope('depth', new DepthScope())
            ->withoutIntermediateScope('depth')
            ->get();

        $this->assertEquals([50, 80], $posts->pluck('id')->all());
    }

    public function testWithoutIntermediateScopeWithObject(): void
    {
        $posts = User::find(4)->posts()->withoutIntermediateScope(new SoftDeletingScope())->get();

        $this->assertEquals([70, 90], $posts->pluck('id')->all());
    }

    public function testWithoutIntermediateScopes(): void
    {
        $posts = User::find(2)->posts()
            ->withIntermediateScope('depth', new DepthScope())
            ->withoutIntermediateScopes()
            ->get();

        $this->assertEquals([50, 80], $posts->pluck('id')->all());
    }

    public function testIntermediateScopes(): void
    {
        $relationship = User::find(2)->posts()->withIntermediateScope('depth', new DepthScope());

        $this->assertArrayHasKey('depth', $relationship->intermediateScopes());
    }

    public function testRemovedIntermediateScopes(): void
    {
        $relationship = User::find(2)->posts()
            ->withIntermediateScope('depth', new DepthScope())
            ->withoutIntermediateScope('depth');

        $this->assertSame(['depth'], $relationship->removedIntermediateScopes());
    }
}
