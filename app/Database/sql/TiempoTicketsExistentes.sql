-- Carga tiempo de trabajo para los tickets que ya existían antes del cronómetro.
-- Requiere TiempoDeTrabajo.sql. Se puede ejecutar varias veces: solo toca tickets
-- con responsable que todavía no tienen ninguna sesión en TicketTimeLogs.
--
--   En progreso -> sesión CORRIENDO desde la fecha de creación del ticket
--                  (el reloj sigue hasta que el responsable pulse Finalizar o cierre).
--   Cerrado     -> sesión cerrada: de la creación al último mensaje del agente
--                  (aproximación, porque Tickets no guarda la fecha de cierre;
--                  si no hay mensajes del agente queda en 0).
--   Abierto / sin responsable -> no se tocan.
--
-- Ajusta @Confirmar = 1 para aplicar; con 0 solo muestra lo que se insertaría.

DECLARE @Confirmar bit = 0;

;WITH Base AS (
    SELECT t.IdTicket, t.AssignedUserId, u.FullName, t.Status, t.CreatedAt,
           (SELECT MAX(m.CreatedAt) FROM dbo.TicketMessages m
             WHERE m.IdTicket = t.IdTicket AND m.SenderType = 'agente') AS LastAgentMsg
    FROM dbo.Tickets t
    JOIN dbo.Users u ON u.IdUser = t.AssignedUserId
    WHERE t.Status IN ('en_progreso', 'cerrado')
      AND NOT EXISTS (SELECT 1 FROM dbo.TicketTimeLogs l WHERE l.IdTicket = t.IdTicket)
)
SELECT IdTicket, AssignedUserId, FullName, Status,
       CreatedAt AS StartedAt,
       CASE WHEN Status = 'cerrado'
            THEN CASE WHEN LastAgentMsg > CreatedAt THEN LastAgentMsg ELSE CreatedAt END
       END AS EndedAt
INTO #Carga
FROM Base;

SELECT * FROM #Carga ORDER BY IdTicket;

IF @Confirmar = 1
BEGIN
    INSERT INTO dbo.TicketTimeLogs (IdTicket, IdUser, UserName, StartedAt, EndedAt)
    SELECT IdTicket, AssignedUserId, FullName, StartedAt, EndedAt FROM #Carga;

    PRINT CONCAT('Sesiones insertadas: ', @@ROWCOUNT);
END
ELSE
    PRINT 'Vista previa. Cambia @Confirmar a 1 para insertar.';

DROP TABLE #Carga;
