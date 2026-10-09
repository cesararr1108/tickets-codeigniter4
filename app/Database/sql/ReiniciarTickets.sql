-- ============================================================================
-- REINICIAR TICKETS: borra TODOS los tickets y lo que depende de ellos
-- (mensajes, adjuntos, escalamientos y respuestas de formularios) y deja el
-- contador en 0 para que el próximo ticket sea el TK-00001.
--
-- NO toca: compañías, sucursales, categorías, subcategorías, formularios,
-- usuarios, roles, sedes de usuarios, reglas de notificación ni tokens push.
--
-- ES IRREVERSIBLE. Antes de ejecutarlo haz una copia de seguridad:
--   BACKUP DATABASE [TicketsDB] TO DISK = N'C:\Backups\TicketsDB_antes_de_reiniciar.bak' WITH INIT;
--
-- Para ejecutarlo de verdad, cambia @Confirmar a 1. Con 0 solo muestra cuántos
-- registros se borrarían.
-- ============================================================================
USE [TicketsDB];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Confirmar bit = 0;   -- <<< cámbialo a 1 para borrar

-- Hijos primero y Tickets al final.
DECLARE @tablas TABLE (Orden int PRIMARY KEY, Nombre sysname);
INSERT INTO @tablas (Orden, Nombre) VALUES
    (1, N'TicketMessages'),
    (2, N'TicketAttachments'),
    (3, N'TicketEscalations'),
    (4, N'TicketFormAnswers'),
    (5, N'Tickets');

DECLARE @orden int = 1, @n sysname, @sql nvarchar(max), @filas int;

-- 1) Resumen de lo que hay ahora
PRINT 'Registros actuales:';
WHILE @orden <= 5
BEGIN
    SELECT @n = Nombre FROM @tablas WHERE Orden = @orden;

    IF OBJECT_ID(N'dbo.' + @n, N'U') IS NOT NULL
    BEGIN
        SET @sql = N'SELECT @f = COUNT(*) FROM dbo.' + QUOTENAME(@n);
        EXEC sp_executesql @sql, N'@f int OUTPUT', @f = @filas OUTPUT;
        PRINT '  ' + @n + ': ' + CAST(@filas AS varchar(20));
    END
    ELSE
        PRINT '  ' + @n + ': (la tabla no existe, se omite)';

    SET @orden += 1;
END

IF @Confirmar = 0
BEGIN
    PRINT '';
    PRINT 'Simulación: no se borró nada. Cambia @Confirmar a 1 para ejecutar.';
    RETURN;
END

-- 2) Borrado
BEGIN TRAN;

SET @orden = 1;
WHILE @orden <= 5
BEGIN
    SELECT @n = Nombre FROM @tablas WHERE Orden = @orden;

    IF OBJECT_ID(N'dbo.' + @n, N'U') IS NOT NULL
    BEGIN
        SET @sql = N'SELECT @f = COUNT(*) FROM dbo.' + QUOTENAME(@n);
        EXEC sp_executesql @sql, N'@f int OUTPUT', @f = @filas OUTPUT;

        SET @sql = N'DELETE FROM dbo.' + QUOTENAME(@n);
        EXEC (@sql);

        -- Si la tabla tenía registros, el contador vuelve a 0 (el siguiente será 1).
        IF @filas > 0 AND OBJECTPROPERTY(OBJECT_ID(N'dbo.' + @n), 'TableHasIdentity') = 1
        BEGIN
            SET @sql = N'DBCC CHECKIDENT (N''dbo.' + @n + N''', RESEED, 0) WITH NO_INFOMSGS';
            EXEC (@sql);
        END
    END

    SET @orden += 1;
END

COMMIT TRAN;

PRINT '';
PRINT 'Listo: tickets reiniciados. El próximo ticket será el TK-00001.';
GO
