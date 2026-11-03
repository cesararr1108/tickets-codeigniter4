-- Desarrollos: hitos por tarea, estadística del cierre de mes y reporte.
-- Requiere app/Database/sql/Desarrollos.sql. Ejecutar en TicketsDB (se puede
-- ejecutar varias veces sin duplicar nada).
--
-- Reglas:
--  * Cada tarea tiene su fecha de cumplimiento (hito). La meta de un mes son
--    las tareas que vencen en ese mes; el avance del proyecto es aparte.
--  * Al cerrar el mes TODOS los desarrollos abiertos pasan al mes siguiente;
--    las tareas vencidas sin terminar pasan con fecha al fin del mes siguiente
--    (OriginalDueDate guarda la fecha con la que se comprometieron).
--  * El cierre guarda la estadística (completos, sin asignar, sin empezar,
--    en curso, cumplidos vs. no cumplidos) y el detalle por desarrollo para
--    el reporte.

-- Hito de cada tarea.
IF COL_LENGTH(N'dbo.DevelopmentTasks', N'DueDate') IS NULL
    ALTER TABLE [dbo].[DevelopmentTasks] ADD [DueDate] date NULL;
GO
IF COL_LENGTH(N'dbo.DevelopmentTasks', N'OriginalDueDate') IS NULL
    ALTER TABLE [dbo].[DevelopmentTasks] ADD [OriginalDueDate] date NULL;
GO
IF COL_LENGTH(N'dbo.DevelopmentTasks', N'CarriedFromMonth') IS NULL
    ALTER TABLE [dbo].[DevelopmentTasks] ADD [CarriedFromMonth] char(7) COLLATE Modern_Spanish_CI_AS NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_DevelopmentTasks_DueDate' AND object_id = OBJECT_ID(N'dbo.DevelopmentTasks'))
    CREATE NONCLUSTERED INDEX [IX_DevelopmentTasks_DueDate] ON [dbo].[DevelopmentTasks] ([DueDate], [IdDevelopment]);
GO

-- Estadística del cierre.
IF COL_LENGTH(N'dbo.DevelopmentMonthClosures', N'Unassigned') IS NULL
    ALTER TABLE [dbo].[DevelopmentMonthClosures] ADD
      [Unassigned] int CONSTRAINT [DF_DevelopmentMonthClosures_Unassigned] DEFAULT 0 NOT NULL,
      [NotStarted] int CONSTRAINT [DF_DevelopmentMonthClosures_NotStarted] DEFAULT 0 NOT NULL,
      [InProgress] int CONSTRAINT [DF_DevelopmentMonthClosures_InProgress] DEFAULT 0 NOT NULL,
      [Eligible] int CONSTRAINT [DF_DevelopmentMonthClosures_Eligible] DEFAULT 0 NOT NULL,       -- asignados con hitos del mes o con requerimiento
      [Met] int CONSTRAINT [DF_DevelopmentMonthClosures_Met] DEFAULT 0 NOT NULL,                 -- cumplieron la meta del mes
      [MilestonesDue] int CONSTRAINT [DF_DevelopmentMonthClosures_MilestonesDue] DEFAULT 0 NOT NULL,
      [MilestonesDone] int CONSTRAINT [DF_DevelopmentMonthClosures_MilestonesDone] DEFAULT 0 NOT NULL;
GO

-- Detalle por desarrollo del cierre (base del reporte).
IF OBJECT_ID(N'dbo.DevelopmentMonthClosureItems', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentMonthClosureItems] (
      [IdItem] int IDENTITY(1, 1) NOT NULL,
      [PlanMonth] char(7) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [IdDevelopment] int NOT NULL,
      [IdTicket] int NOT NULL,
      [Subject] nvarchar(255) COLLATE Modern_Spanish_CI_AS NULL,
      [CodCompanies] varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
      [AssignedUserId] varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
      [ProgrammerName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL,
      [State] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Bucket] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,     -- completo | sin_asignar | sin_empezar | en_curso
      [CommittedDate] date NULL,
      [MilestonesDue] int NOT NULL,
      [MilestonesDone] int NOT NULL,
      [HasRequirement] bit NOT NULL,
      [Eligible] bit NOT NULL,
      [Met] bit NOT NULL,
      [GoalPct] int NULL,                                               -- meta del mes
      [ProjectPct] int NOT NULL,                                        -- avance del proyecto
      [CarriedOver] bit NOT NULL,
      [ApprovedAt] datetime2(0) NULL,
      CONSTRAINT [PK_DevelopmentMonthClosureItems] PRIMARY KEY CLUSTERED ([IdItem]),
      CONSTRAINT [FK_DevelopmentMonthClosureItems_Closure] FOREIGN KEY ([PlanMonth])
        REFERENCES [dbo].[DevelopmentMonthClosures] ([PlanMonth])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_DevelopmentMonthClosureItems_Month] ON [dbo].[DevelopmentMonthClosureItems] ([PlanMonth]);
END
GO
