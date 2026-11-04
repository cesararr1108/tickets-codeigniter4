-- Proyectos dentro de Desarrollos: tipo (desarrollo | proyecto), lo que se
-- requiere, presupuesto (presupuestado vs. ejecutado), responsable por
-- actividad y reprogramaciones de la entrega (por incidentes u otros motivos).
-- Requiere Desarrollos.sql y DesarrollosHitosYCierre.sql. Ejecutar en
-- TicketsDB (se puede ejecutar varias veces sin duplicar nada).
--
-- Reglas:
--  * Un ticket es "proyecto" si su categoría/subcategoría lo dice o si llegó
--    con el formulario de proyecto del widget (Config\Tickets::$projectForms).
--    El administrador confirma el tipo al planearlo y lo puede cambiar.
--  * OriginalCommittedDate es la primera entrega comprometida (línea base);
--    CommittedDate es la vigente. Cada cambio queda en DevelopmentReschedules.
--  * Un incidente con días de impacto puede reprogramar la entrega: la nueva
--    fecha es la vigente + días de impacto (una vez por incidente).

IF COL_LENGTH(N'dbo.Developments', N'Kind') IS NULL
    ALTER TABLE [dbo].[Developments] ADD [Kind] varchar(15) COLLATE Modern_Spanish_CI_AS
        CONSTRAINT [DF_Developments_Kind] DEFAULT 'desarrollo' NOT NULL;            -- desarrollo | proyecto
GO
IF COL_LENGTH(N'dbo.Developments', N'Requirements') IS NULL
    ALTER TABLE [dbo].[Developments] ADD [Requirements] nvarchar(4000) COLLATE Modern_Spanish_CI_AS NULL;  -- lo que se requiere
GO
IF COL_LENGTH(N'dbo.Developments', N'OriginalCommittedDate') IS NULL
    ALTER TABLE [dbo].[Developments] ADD [OriginalCommittedDate] date NULL;  -- línea base de la entrega
GO
-- Línea base para los que ya estaban planeados.
UPDATE [dbo].[Developments] SET [OriginalCommittedDate] = [CommittedDate]
WHERE [OriginalCommittedDate] IS NULL AND [CommittedDate] IS NOT NULL;
GO

-- Responsable de cada actividad (puede ser alguien fuera de TI).
IF COL_LENGTH(N'dbo.DevelopmentTasks', N'ResponsibleName') IS NULL
    ALTER TABLE [dbo].[DevelopmentTasks] ADD [ResponsibleName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL;
GO

-- Presupuesto por rubro.
IF OBJECT_ID(N'dbo.DevelopmentBudgetItems', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentBudgetItems] (
      [IdBudgetItem] int IDENTITY(1, 1) NOT NULL,
      [IdDevelopment] int NOT NULL,
      [Concept] nvarchar(255) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Planned] decimal(14, 2) NOT NULL,                                -- presupuestado
      [Spent] decimal(14, 2) CONSTRAINT [DF_DevelopmentBudgetItems_Spent] DEFAULT 0 NOT NULL,  -- ejecutado
      [CreatedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_DevelopmentBudgetItems_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_DevelopmentBudgetItems] PRIMARY KEY CLUSTERED ([IdBudgetItem]),
      CONSTRAINT [FK_DevelopmentBudgetItems_Development] FOREIGN KEY ([IdDevelopment])
        REFERENCES [dbo].[Developments] ([IdDevelopment])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_DevelopmentBudgetItems_Development] ON [dbo].[DevelopmentBudgetItems] ([IdDevelopment]);
END
GO

-- Historial de cambios de la entrega comprometida.
IF OBJECT_ID(N'dbo.DevelopmentReschedules', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentReschedules] (
      [IdReschedule] int IDENTITY(1, 1) NOT NULL,
      [IdDevelopment] int NOT NULL,
      [IdIssue] int NULL,                                               -- incidente que la causó
      [PreviousDate] date NULL,
      [NewDate] date NOT NULL,
      [Reason] nvarchar(1000) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [ChangedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_DevelopmentReschedules_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_DevelopmentReschedules] PRIMARY KEY CLUSTERED ([IdReschedule]),
      CONSTRAINT [FK_DevelopmentReschedules_Development] FOREIGN KEY ([IdDevelopment])
        REFERENCES [dbo].[Developments] ([IdDevelopment])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_DevelopmentReschedules_Development] ON [dbo].[DevelopmentReschedules] ([IdDevelopment], [CreatedAt]);
END
GO

-- Tipo, presupuesto y reprogramación en el detalle del cierre de mes.
IF COL_LENGTH(N'dbo.DevelopmentMonthClosureItems', N'Kind') IS NULL
    ALTER TABLE [dbo].[DevelopmentMonthClosureItems] ADD
      [Kind] varchar(15) COLLATE Modern_Spanish_CI_AS CONSTRAINT [DF_DevelopmentMonthClosureItems_Kind] DEFAULT 'desarrollo' NOT NULL,
      [BudgetPlanned] decimal(14, 2) CONSTRAINT [DF_DevelopmentMonthClosureItems_BudgetPlanned] DEFAULT 0 NOT NULL,
      [BudgetSpent] decimal(14, 2) CONSTRAINT [DF_DevelopmentMonthClosureItems_BudgetSpent] DEFAULT 0 NOT NULL,
      [RescheduledDays] int CONSTRAINT [DF_DevelopmentMonthClosureItems_RescheduledDays] DEFAULT 0 NOT NULL;
GO
