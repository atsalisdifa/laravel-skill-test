<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_index_return_paginated_list_of_active_posts(): void
    {
        $author = User::factory()->create();
        $activePosts = Post::factory()->count(25)->for($author)->create();
        $draftPost = Post::factory()->for($author)->draft()->create();
        $scheduledPost = Post::factory()->for($author)->scheduled()->create();

        $response = $this->getJson(route('posts.index'));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'content',
                    'is_draft',
                    'published_at',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
            ],
            'per_page',
            'total',
        ]);

        $response->assertJsonPath('per_page', 20);
        $response->assertJsonPath('total', 25);

        $response->assertJsonMissing(['data.*.id' => $draftPost->id]);
        $response->assertJsonMissing(['data.*.id' => $scheduledPost->id]);
    }

    public function test_authenticated_user_can_create_a_post(): void
    {
        $author = User::factory()->create();
        $postData = Post::factory()->make()->toArray();

        $response = $this->actingAs($author)->postJson(route('posts.store'), $postData);
        $response->assertCreated();

        $this->assertDatabaseHas('posts', ['title' => $postData['title'], 'user_id' => $author->id]);
    }

    public function test_unauthenticated_user_cannot_create_a_post(): void
    {
        $postData = Post::factory()->make()->toArray();

        $response = $this->postJson(route('posts.store'), $postData);
        $response->assertUnauthorized();
    }

    #[DataProvider('invalidPostDataProvider')]
    public function test_post_creation_fails_with_invalid_data(array $invalidData, array|string $expectedErrorKey): void
    {
        $author = User::factory()->create();

        $response = $this->actingAs($author)->postJson(route('posts.store'), $invalidData);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors($expectedErrorKey);
    }

    public function test_posts_show_can_retrieve_an_active_post_and_404_for_draft_or_scheduled_post(): void
    {
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();
        $draftPost = Post::factory()->for($author)->draft()->create();
        $scheduledPost = Post::factory()->for($author)->scheduled()->create();

        $response = $this->getJson(route('posts.show', $activePost));
        $response->assertOk();
        $response->assertJsonPath('id', $activePost->id);

        $this->getJson(route('posts.show', $draftPost))->assertNotFound();
        $this->getJson(route('posts.show', $scheduledPost))->assertNotFound();
    }

    public function test_author_can_update_their_post(): void
    {
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();
        $updatedData = ['title' => 'Updated Title', 'content' => 'New content.'];

        $response = $this->actingAs($author)->putJson(route('posts.update', $activePost), $updatedData);
        $response->assertOk();

        $this->assertDatabaseHas('posts', ['id' => $activePost->id, 'title' => 'Updated Title']);
    }

    public function test_non_author_cannot_update_a_post(): void
    {
        $nonAuthor = User::factory()->create();
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();
        $updatedData = ['title' => 'Updated Title', 'content' => 'New content.'];

        $response = $this->actingAs($nonAuthor)->putJson(route('posts.update', $activePost), $updatedData);
        $response->assertForbidden();

        $this->assertDatabaseMissing('posts', ['id' => $activePost->id, 'title' => 'Attempted Update']);
    }

    public function test_unauthenticated_user_cannot_update_a_post(): void
    {
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();
        $updatedData = ['title' => 'Updated Title', 'content' => 'New content.'];

        $response = $this->putJson(route('posts.update', $activePost), $updatedData);
        $response->assertUnauthorized();
    }

    #[DataProvider('invalidPostDataProvider')]
    public function test_post_update_fails_with_invalid_data(array $invalidData, array|string $expectedErrorKey): void
    {
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();

        $response = $this->actingAs($author)->putJson(route('posts.update', $activePost), $invalidData);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors($expectedErrorKey);
    }

    public function test_author_can_delete_their_post(): void
    {
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();

        $response = $this->actingAs($author)->deleteJson(route('posts.destroy', $activePost));
        $response->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $activePost->id]);
    }

    public function test_non_author_cannot_delete_a_post(): void
    {
        $nonAuthor = User::factory()->create();
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();

        $response = $this->actingAs($nonAuthor)->deleteJson(route('posts.destroy', $activePost));
        $response->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $activePost->id]);
    }

    public function test_unauthenticated_user_cannot_delete_a_post(): void
    {
        $author = User::factory()->create();
        $activePost = Post::factory()->for($author)->create();

        $response = $this->deleteJson(route('posts.destroy', $activePost));
        $response->assertUnauthorized();
    }

    public static function invalidPostDataProvider(): array
    {
        return [
            'missing title (required)' => [
                ['content' => 'some content', 'is_draft' => 1],
                'title',
            ],
            'title is too long (max:255)' => [
                ['title' => str_repeat('a', 256), 'content' => 'some content'],
                'title',
            ],
            'missing content (required)' => [
                ['title' => 'some title', 'is_draft' => 1],
                'content',
            ],
            'is_draft is not a boolean' => [
                ['title' => 'some title', 'content' => 'some content', 'is_draft' => 'not-a-bool'],
                'is_draft',
            ],
            'published_at is not a valid date' => [
                ['title' => 'some title', 'content' => 'some content', 'published_at' => 'not-a-date-at-all'],
                'published_at',
            ],
            'published_at is missing for a non-draft post' => [
                ['title' => 'some title', 'content' => 'some content', 'is_draft' => 0],
                'published_at',
            ],
        ];
    }
}
