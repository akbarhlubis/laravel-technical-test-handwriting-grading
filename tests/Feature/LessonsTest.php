<?php

namespace Tests\Feature;

use App\Models\Lesson;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class LessonsTest extends TestCase
{
    public function test_lessons_returns_the_lessons_inertia_page_shape(): void
    {
        $lesson = new Lesson;
        $lesson->setRawAttributes([
            'id' => '865dc30c-a821-4589-b088-a4a96d883541',
            'title' => 'Lesson One',
            'moe_level' => 'P2',
            'word_list' => '{"caochang","litang","laoshi"}',
        ]);

        $model = Mockery::mock(Lesson::class);
        $query = Mockery::mock();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $query->shouldReceive('orderBy')->once()->with('created_at')->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn(new Collection([$lesson]));

        $this->instance(Lesson::class, $model);

        $response = $this->get('/lessons');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Lessons')
            ->has('lessons', 1)
            ->where('lessons.0.id', '865dc30c-a821-4589-b088-a4a96d883541')
            ->where('lessons.0.moe_level', 'P2')
            ->where('lessons.0.word_list', ['caochang', 'litang', 'laoshi']));
    }
}
