<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $type
 * @property string|null $name
 * @property int $created_by
 */
class Conversation extends Model
{
    protected $fillable = [
        'type',
        'name',
        'created_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Nom affiché à un participant donné : le nom du groupe s'il est
     * renseigné, sinon la liste des autres participants (conversation
     * directe, ou groupe non nommé).
     */
    public function displayName(User $viewer): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return $this->participants
            ->reject(fn (User $participant) => $participant->id === $viewer->id)
            ->pluck('name')
            ->implode(', ');
    }

    /**
     * Nombre de messages postés dans cette conversation après la dernière
     * lecture de $user, hors ses propres messages. Requête directe sur la
     * table pivot (pas la relation participants() éventuellement chargée
     * pour l'aperçu) afin de rester correct quel que soit ce qui a été
     * eager-load par l'appelant.
     */
    public function unreadCountFor(User $user): int
    {
        $lastReadAt = DB::table('conversation_user')
            ->where('conversation_id', $this->id)
            ->where('user_id', $user->id)
            ->value('last_read_at');

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($lastReadAt !== null, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }
}
