-- Seguimiento de desarrollos: plan del mes, tareas, recursos, inconvenientes,
-- evidencia de cierre y cierre de mes. Un desarrollo nace de un ticket cuya
-- categoría está en Config\Tickets::$developmentCategories (ej. "Desarrollo").
-- Ejecutar en TicketsDB (se puede ejecutar varias veces sin duplicar nada).

IF OBJECT_ID(N'dbo.Developments', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[Developments] (
      [IdDevelopment] int IDENTITY(1, 1) NOT NULL,
      [IdTicket] int NOT NULL,
      [AssignedUserId] varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
      [PlanMonth] char(7) COLLATE Modern_Spanish_CI_AS NULL,          -- AAAA-MM
      [CommittedDate] date NULL,                                       -- la fija el administrador
      [EstimatedDate] date NULL,                                       -- la actualiza el programador
      [CarriedFromMonth] char(7) COLLATE Modern_Spanish_CI_AS NULL,   -- viene de un mes cerrado
      [ApprovedAt] datetime2(0) NULL,
      [ApprovedBy] varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
      [ApprovedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_Developments_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_Developments] PRIMARY KEY CLUSTERED ([IdDevelopment]),
      CONSTRAINT [UQ_Developments_Ticket] UNIQUE ([IdTicket]),
      CONSTRAINT [FK_Developments_Ticket] FOREIGN KEY ([IdTicket])
        REFERENCES [dbo].[Tickets] ([IdTicket])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_Developments_Month] ON [dbo].[Developments] ([PlanMonth], [AssignedUserId]);
END
GO

IF OBJECT_ID(N'dbo.DevelopmentTasks', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentTasks] (
      [IdTask] int IDENTITY(1, 1) NOT NULL,
      [IdDevelopment] int NOT NULL,
      [Description] nvarchar(255) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Hours] decimal(6, 1) NOT NULL,
      [DoneAt] datetime2(0) NULL,
      [DoneBy] varchar(20) COLLATE Modern_Spanish_CI_AS NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_DevelopmentTasks_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_DevelopmentTasks] PRIMARY KEY CLUSTERED ([IdTask]),
      CONSTRAINT [FK_DevelopmentTasks_Development] FOREIGN KEY ([IdDevelopment])
        REFERENCES [dbo].[Developments] ([IdDevelopment])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_DevelopmentTasks_Development] ON [dbo].[DevelopmentTasks] ([IdDevelopment]);
END
GO

IF OBJECT_ID(N'dbo.DevelopmentResources', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentResources] (
      [IdResource] int IDENTITY(1, 1) NOT NULL,
      [IdDevelopment] int NOT NULL,
      [Description] nvarchar(255) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [RequestedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL,
      [ReadyAt] datetime2(0) NULL,                                     -- NULL = solicitado, aún no disponible
      [CreatedAt] datetime2(0) CONSTRAINT [DF_DevelopmentResources_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_DevelopmentResources] PRIMARY KEY CLUSTERED ([IdResource]),
      CONSTRAINT [FK_DevelopmentResources_Development] FOREIGN KEY ([IdDevelopment])
        REFERENCES [dbo].[Developments] ([IdDevelopment])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_DevelopmentResources_Development] ON [dbo].[DevelopmentResources] ([IdDevelopment]);
END
GO

IF OBJECT_ID(N'dbo.DevelopmentIssues', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentIssues] (
      [IdIssue] int IDENTITY(1, 1) NOT NULL,
      [IdDevelopment] int NOT NULL,
      [IssueType] varchar(15) COLLATE Modern_Spanish_CI_AS NOT NULL,  -- riesgo | bloqueante
      [Description] nvarchar(2000) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [ImpactDays] int CONSTRAINT [DF_DevelopmentIssues_ImpactDays] DEFAULT 0 NOT NULL,
      [ReportedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [ResolvedAt] datetime2(0) NULL,
      [ResolvedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_DevelopmentIssues_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_DevelopmentIssues] PRIMARY KEY CLUSTERED ([IdIssue]),
      CONSTRAINT [FK_DevelopmentIssues_Development] FOREIGN KEY ([IdDevelopment])
        REFERENCES [dbo].[Developments] ([IdDevelopment])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_DevelopmentIssues_Development] ON [dbo].[DevelopmentIssues] ([IdDevelopment], [ResolvedAt]);
END
GO

IF OBJECT_ID(N'dbo.DevelopmentEvidence', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentEvidence] (
      [IdEvidence] int IDENTITY(1, 1) NOT NULL,
      [IdDevelopment] int NOT NULL,
      [FileName] nvarchar(255) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [FilePath] nvarchar(500) COLLATE Modern_Spanish_CI_AS NOT NULL, -- relativo a writable/uploads
      [UploadedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NULL,
      [CreatedAt] datetime2(0) CONSTRAINT [DF_DevelopmentEvidence_CreatedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_DevelopmentEvidence] PRIMARY KEY CLUSTERED ([IdEvidence]),
      CONSTRAINT [FK_DevelopmentEvidence_Development] FOREIGN KEY ([IdDevelopment])
        REFERENCES [dbo].[Developments] ([IdDevelopment])
        ON UPDATE NO ACTION
        ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX [IX_DevelopmentEvidence_Development] ON [dbo].[DevelopmentEvidence] ([IdDevelopment]);
END
GO

-- Cierre de mes: comprometidos vs. realizados y cuántos pasaron al mes siguiente.
IF OBJECT_ID(N'dbo.DevelopmentMonthClosures', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[DevelopmentMonthClosures] (
      [PlanMonth] char(7) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [Committed] int NOT NULL,
      [Delivered] int NOT NULL,
      [CarriedOver] int NOT NULL,
      [ClosedBy] varchar(20) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [ClosedByName] nvarchar(150) COLLATE Modern_Spanish_CI_AS NOT NULL,
      [ClosedAt] datetime2(0) CONSTRAINT [DF_DevelopmentMonthClosures_ClosedAt] DEFAULT sysutcdatetime() NOT NULL,
      CONSTRAINT [PK_DevelopmentMonthClosures] PRIMARY KEY CLUSTERED ([PlanMonth])
    );
END
GO
