-- 1) Orden manual de los desarrollos (Developments.SortOrder).
-- 2) Compras (diademas, mouse, etc.): tickets que nacen de otro ticket y no
--    cuentan en dashboard ni reportes (Tickets.IsPurchase, Tickets.ParentTicketId).
-- Ejecutar en TicketsDB (se puede ejecutar varias veces sin duplicar nada).

IF COL_LENGTH(N'dbo.Developments', N'SortOrder') IS NULL
    ALTER TABLE [dbo].[Developments] ADD [SortOrder] int NULL;
GO

IF COL_LENGTH(N'dbo.Tickets', N'IsPurchase') IS NULL
    ALTER TABLE [dbo].[Tickets] ADD [IsPurchase] bit NOT NULL CONSTRAINT [DF_Tickets_IsPurchase] DEFAULT 0;
GO

IF COL_LENGTH(N'dbo.Tickets', N'ParentTicketId') IS NULL
    ALTER TABLE [dbo].[Tickets] ADD [ParentTicketId] int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Tickets_IsPurchase' AND object_id = OBJECT_ID(N'dbo.Tickets'))
    CREATE NONCLUSTERED INDEX [IX_Tickets_IsPurchase] ON [dbo].[Tickets] ([IsPurchase], [CreatedAt]);
GO
