<?php

namespace Database\Factories;

use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mail\MailEventTemplate>
 */
class MailEventTemplateFactory extends Factory
{
    protected $model = MailEventTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mail_event_id' => MailEvent::factory(),
            'name' => fake()->sentence(2),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'variables' => [],
            'is_active' => true,
            'is_default' => false,
        ];
    }
}
