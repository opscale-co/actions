import Tool from './pages/Tool'

Nova.booting((app, store) => {
  Nova.inertia(':namespace_tool_name', Tool)
})
