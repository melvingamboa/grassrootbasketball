<?php

namespace App\Events;

use App\Http\Resources\PublicGameResource;
use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameLiveUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Game $game) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('games.'.$this->game->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.updated';
    }

    public function broadcastWith(): array
    {
        return ['game' => (new PublicGameResource($this->game))->resolve()];
    }
}
