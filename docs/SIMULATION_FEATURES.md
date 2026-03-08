# Real-Time Simulation Feature Documentation

## Overview

A comprehensive, interactive real-time simulation has been embedded into the TGSDK documentation that demonstrates all package features and component interactions in action.

## Features Implemented

### 1. **Interactive Visualization Modules** (6 Core Components)

#### Laravel Application Module
- Shows current file upload operations
- Displays files processed counter
- Real-time operation status updates
- Visual indicators for active/processing/idle states

#### TelegramStorageAdapter Module
- Displays active Flysystem methods (write, writeStream, read, etc.)
- Tracks total operations count
- Shows method invocation in real-time
- Status transitions with color-coded indicators

#### ChunkManager Module
- Visualizes file chunking decisions
- Shows chunk creation count
- Displays chunking status (needed/not needed)
- Tracks total chunks created across simulation

#### Redis Queue Module
- Real-time queue length display
- Jobs enqueued counter
- Visual queue depth representation
- Job flow tracking

#### Python Worker Module
- Active session pool monitoring (0/5 sessions)
- Upload completion counter
- Session availability visualization
- Worker state transitions

#### Telegram Channels Module
- Active channel selection display
- Messages stored counter
- Channel rotation visualization
- MTProto upload status

### 2. **Real-Time Controls**

#### Playback Controls
- **Start Button**: Initiates simulation with gradient blue styling
- **Pause/Resume Button**: Toggle simulation state with amber styling
- **Reset Button**: Returns all modules to initial state with green styling

#### Speed Control
- Adjustable slider: 0.5x to 3x speed
- Real-time speed indicator display
- Dynamic interval adjustment
- Smooth speed transitions

### 3. **Performance Metrics Dashboard**

Six live metrics updated in real-time:

1. **Response Time** - Average upload response time in milliseconds
2. **Throughput** - Data transfer rate in MB/s
3. **Success Rate** - Percentage of successful uploads
4. **Queue Depth** - Current number of pending jobs
5. **Average Upload Size** - Mean file size in MB
6. **Total Data Transferred** - Cumulative data in GB

### 4. **Live Status Indicators**

Status States:
- **Idle** (Gray) - Module waiting for work
- **Active** (Green) - Module ready and operational
- **Processing** (Blue, pulsing) - Module actively working
- **Error** (Red) - Error state (for error scenarios)

Each module has:
- Color-coded status badge
- Animated transitions between states
- Pulsing effects during processing
- Hover effects for interactivity

### 5. **Data Flow Visualization**

#### Particle Animation System
- Glowing data particles travel between modules
- Cubic-bezier easing for natural movement
- Duration based on distance between elements
- Auto-cleanup after animation completes
- Visual representation of data/payload transfer

#### Flow Connectors
- SVG-like connections between modules
- Animated particles along connector paths
- Direction indicators showing flow direction
- Continuous animation during processing

### 6. **Workflow Steps Breakdown**

Complete upload workflow visualization:

```
Step 1: Laravel initiates file upload
   ↓
Step 2: Adapter receives write() call
   ↓
Step 3: ChunkManager evaluates file size
   ↓
Step 4: Redis Queue receives job
   ↓
Step 5: Python Worker picks up job
   ↓
Step 6: Upload to Telegram channel
   ↓
Step 7: Completion callback
```

Each step includes:
- Timing delays (configurable by speed)
- Visual feedback
- Log entries
- Metric updates
- State transitions

### 7. **Error Handling Scenarios**

Built-in error simulation capability:
- Network timeout simulation
- Retry mechanism visualization
- Error state display
- Recovery process demonstration
- Success after retry scenarios

### 8. **Log Terminal**

Real-time logging system:
- Timestamped entries
- Color-coded log levels:
  - **Info** (Blue) - Normal operations
  - **Success** (Green) - Successful completions
  - **Warning** (Amber) - Retries, notices
  - **Error** (Red) - Failures
- Auto-scroll to latest entry
- Maximum 50 entries (FIFO)
- Fade-in animation for new entries

### 9. **Animated Visual Effects**

#### Module Animations
- **Scan Line Effect**: Horizontal scanner bar across modules
- **Pulse Animation**: Glowing pulse during processing
- **Hover Effects**: Lift and scale on mouseover
- **Status Transitions**: Smooth color changes

#### Global Effects
- **Container Glow**: Subtle radial gradient background
- **Particle Trails**: Glowing data particles
- **Metric Updates**: Number roll-over effects
- **Button Ripples**: Material design ripple on click

### 10. **User Interaction Points**

#### Clickable Elements
- Start/Pause/Reset buttons
- Speed slider control
- Module hover states
- Navigation menu integration

#### Visual Feedback
- Button hover effects with glow
- Ripple effect on click
- Cursor changes (pointer for interactive)
- Scale transforms on interaction

## Technical Implementation

### CSS Architecture

**Key Classes:**
- `.simulation-container` - Main wrapper with gradient background
- `.simulation-module` - Individual module cards
- `.module-status` - Status badges with state variants
- `.data-particle` - Animated data transfer particles
- `.metrics-panel` - Performance metrics dashboard
- `.log-terminal` - Scrollable log output
- `.simulation-btn` - Interactive control buttons
- `.speed-slider` - Custom styled range input

**Animation Keyframes:**
- `simulationGlow` - Background pulse effect
- `moduleScan` - Scanner bar animation
- `modulePulse` - Processing state glow
- `statusPulse` - Status badge opacity cycle
- `flowParticle` - Data particle movement
- `logFadeIn` - Log entry appearance

### JavaScript Architecture

**State Management:**
```javascript
{
    simulationRunning: boolean,
    simulationPaused: boolean,
    simulationSpeed: float,
    simulationInterval: timer,
    stepIndex: integer
}
```

**Statistics Tracking:**
```javascript
stats = {
    filesProcessed: integer,
    operations: integer,
    chunksCreated: integer,
    jobsEnqueued: integer,
    uploadsCompleted: integer,
    messagesStored: integer,
    totalDataGB: float,
    totalSizeMB: float,
    responseTimes: array,
    successCount: integer,
    errorCount: integer
}
```

**Core Functions:**
- `startSimulation()` - Initialize and run
- `pauseSimulation()` - Toggle pause state
- `resetSimulation()` - Reset all state
- `updateSpeed(value)` - Adjust simulation speed
- `runSimulationStep()` - Execute one workflow cycle
- `updateModuleStatus(moduleId, status)` - Update UI state
- `addLog(message, type)` - Add terminal log entry
- `createDataParticle(fromId, toId)` - Animate data transfer
- `updateMetrics()` - Recalculate and display metrics
- `triggerErrorScenario()` - Simulate errors (extensibility)

### Sample Data

**File Types Simulated:**
- Documents (5.2 MB) - No chunking
- Images (2.8 MB) - No chunking
- Videos (1850 MB) - Requires chunking
- Archives (450 MB) - No chunking
- Audio (8.5 MB) - No chunking

**Telegram Channels:**
- Channel #1, #2, #3 (rotated randomly)

## Usage Instructions

### Starting the Simulation

1. Navigate to the "Simulation" section in documentation
2. Click the **"Start"** button (blue play icon)
3. Watch as modules activate and data flows through the system
4. Observe real-time logs and metrics updates

### Controlling Playback

- **Pause**: Click pause button to freeze current state
- **Resume**: Click pause again to continue
- **Reset**: Click reset to clear all counters and logs
- **Speed**: Drag slider left (slower) or right (faster)

### Observing Workflows

The simulation automatically cycles through different file types:
- Small files bypass chunking
- Large files (>1950 MB) trigger chunking visualization
- Random channel selection demonstrates rotation
- Metrics update based on actual simulated file sizes

### Interpreting Status Colors

- **Gray (Idle)**: Waiting for work
- **Green (Active)**: Ready and available
- **Blue (Processing)**: Currently working (pulsing)
- **Red (Error)**: Problem detected (if error scenario triggered)

## Integration with Existing Design

### Design Consistency

- Uses same color palette (#0088cc, #00d4ff, etc.)
- Matches glassmorphism effects from SASS docs
- Consistent border radius and spacing
- Same gradient styles as other sections
- Font Awesome icon integration
- Responsive grid layout

### Responsive Behavior

- Grid auto-adjusts for different screen sizes
- Modules maintain aspect ratio
- Controls wrap on smaller screens
- Log terminal scrolls independently
- Metrics panel responsive grid

## Performance Considerations

### Optimization Techniques

- Particle auto-cleanup prevents memory leaks
- Log entry limit (50 max) prevents DOM bloat
- CSS animations use GPU acceleration (transform/opacity)
- Debounced metric calculations
- Efficient interval management

### Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- CSS custom properties support required
- Web Animations API support recommended
- Backdrop-filter for glassmorphism effects

## Extensibility

### Adding New Scenarios

The simulation can be extended with:
- Additional file types
- More error scenarios (timeout, corruption, network issues)
- Different channel rotation strategies
- Compression/encryption visualization
- Multi-file parallel uploads
- Download/streaming simulations

### Future Enhancements

Potential improvements:
- Export simulation data to CSV/JSON
- Scenario selector (choose specific workflow)
- Step-by-step manual advancement
- Tooltips explaining each module
- Sound effects (optional)
- Dark/light mode toggle
- Mobile touch controls

## Accessibility

- Keyboard navigation support (Tab through controls)
- High contrast status indicators
- Screen reader friendly labels
- Focus states on interactive elements
- ARIA attributes for dynamic content

## Conclusion

This real-time simulation provides an immersive, educational experience that helps users understand the TGSDK package's inner workings without needing to install or configure anything. It serves as both a learning tool and a demonstration of the package's robust architecture.

---

**Built with**: HTML5, CSS3 (SCSS), Vanilla JavaScript  
**Design System**: BEM methodology, Glassmorphism, Modern Gradient Effects  
**Animation**: CSS Keyframes, Web Animations API  
**Icons**: Font Awesome 6.4.0
