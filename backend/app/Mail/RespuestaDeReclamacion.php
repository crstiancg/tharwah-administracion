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
 * Respuesta del proveedor a una hoja de reclamación, al correo que dejó el
 * consumidor.
 */
class RespuestaDeReclamacion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reclamacion $reclamacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Respuesta a tu hoja de reclamación {$this->reclamacion->codigo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.respuesta-de-reclamacion',
            with: ['r' => $this->reclamacion, 'proveedor' => ReclamacionResource::proveedor()],
        );
    }
}
