<?php

namespace App\Mail;

use App\Http\Resources\ReclamacionResource;
use App\Models\Reclamacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Copia de la hoja de reclamación para el consumidor, al momento de
 * presentarla (art. 4-B del reglamento: con fecha y hora de presentación).
 */
class HojaDeReclamacion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reclamacion $reclamacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Hoja de reclamación {$this->reclamacion->codigo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.hoja-de-reclamacion',
            with: ['r' => $this->reclamacion, 'proveedor' => ReclamacionResource::proveedor()],
        );
    }
}
