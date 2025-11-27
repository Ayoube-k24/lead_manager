<?php

namespace App\Livewire;

use Livewire\Component;

class NotificationsBell extends Component
{
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->updateUnreadCount();
    }

    public function updateUnreadCount(): void
    {
        // For now, we'll use a simple counter
        // In a full implementation, you'd query a notifications table
        $this->unreadCount = 0;
    }

    public function markAllAsRead(): void
    {
        // Mark all notifications as read
        $this->updateUnreadCount();
        $this->dispatch('notifications-marked-read');
    }

    public function render()
    {
        return view('livewire.notifications-bell');
    }
}
