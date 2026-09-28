<?php /** @var array $m Fila de TicketMessages */ ?>
<div class="msg msg-<?= $m['SenderType'] === 'agente' ? 'agent' : 'client' ?>" data-message-id="<?= (int) $m['MessageId'] ?>">
    <span class="avatar avatar-sm"><?= esc(initials($m['SenderName'])) ?></span>
    <div class="msg-bubble">
        <div class="msg-head">
            <strong><?= esc($m['SenderName']) ?></strong>
            <span class="msg-role"><?= $m['SenderType'] === 'agente' ? 'Agente' : 'Cliente' ?></span>
        </div>
        <div class="msg-text"><?= nl2br(esc($m['Message'])) ?></div>
        <time class="msg-time" title="<?= format_date($m['CreatedAt'] ?? null) ?>"><?= format_date($m['CreatedAt'] ?? null, 'd/m H:i') ?></time>
    </div>
</div>
