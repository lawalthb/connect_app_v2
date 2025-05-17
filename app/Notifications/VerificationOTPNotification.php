<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationOTPNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The OTP code.
     *
     * @var string
     */
    protected $otp;

    /**
     * Create a new notification instance.
     *
     * @param string $otp
     * @return void
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->line('Please use the following OTP code to verify your email address.')
            ->line('Your OTP code is: ' . $this->otp)
            ->line('This OTP code will expire in 30 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
