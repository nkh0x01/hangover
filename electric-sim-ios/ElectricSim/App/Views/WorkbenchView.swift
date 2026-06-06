//
//  WorkbenchView.swift
//  ElectricSim
//
//  მთავარი სამუშაო ეკრანი: DIN rail ფარი, კომპონენტების პალიტრა,
//  სადენების დახაზვა (terminal → terminal), ხელსაწყოები და „ჩართე ძაბვა".
//

import SwiftUI

// MARK: - Tools

enum Tool: String, CaseIterable, Identifiable {
    case wire, multimeter, voltageTester, stripper, screwdriver
    var id: String { rawValue }
    var title: String {
        switch self {
        case .wire:          return "სადენი"
        case .multimeter:    return "მულტიმეტრი"
        case .voltageTester: return "ფაზის ინდიკატორი"
        case .stripper:      return "გამცლელი"
        case .screwdriver:   return "სახრახნისი"
        }
    }
    var symbol: String {
        switch self {
        case .wire:          return "cable.connector"
        case .multimeter:    return "gauge.with.dots.needle.bottom.50percent"
        case .voltageTester: return "bolt.badge.checkmark"
        case .stripper:      return "scissors"
        case .screwdriver:   return "screwdriver"
        }
    }
    var hint: String {
        switch self {
        case .wire:          return "შეეხე ორ ფეხს — დაიხაზება სადენი (ფერი ავტომატურად)."
        case .multimeter:    return "შეეხე ორ წერტილს — გაზომავს ძაბვას."
        case .voltageTester: return "შეეხე ფეხს — შეამოწმებს ფაზას."
        case .stripper:      return "სადენის იზოლაციის გაცლა (მონტაჟამდე)."
        case .screwdriver:   return "ფეხის (terminal) მოჭერა."
        }
    }
}

// MARK: - Model

@MainActor
final class WorkbenchModel: ObservableObject {
    let level: Level
    @Published var templates: [String: ComponentTemplate]
    private let solver = CircuitSolver()

    @Published var board: Board
    @Published var placedCounts: [String: Int] = [:]
    @Published var tool: Tool = .wire
    @Published var selectedPort: String?
    @Published var selectedCSA: Double
    @Published var result: SimulationResult?
    @Published var showResult = false
    @Published var levelPassed = false
    @Published var measurement: String?
    @Published var liveAnalysis: NetAnalysis?

    init(level: Level, templates: [String: ComponentTemplate]) {
        self.level = level
        self.templates = templates
        var b = Board(phase: level.phase)
        b.add(ComponentFactory.supply(id: "supply", phase: level.phase))
        self.board = b
        self.selectedCSA = level.palette.compactMap { $0.csaOptions?.first }.first ?? 1.5
    }

    func placed(_ tid: String) -> Int { placedCounts[tid] ?? 0 }
    func canAdd(_ e: PaletteEntry) -> Bool { placed(e.templateId) < e.max }

    func add(_ e: PaletteEntry) {
        guard let t = templates[e.templateId], canAdd(e) else { return }
        let n = placed(e.templateId)
        let inst = t.makeComponent(instanceID: "\(e.templateId)_\(n + 1)", phase: board.phase)
        board.add(inst)
        placedCounts[e.templateId] = n + 1
        resetResult()
    }

    func removeComponent(_ id: String) {
        guard id != "supply" else { return }
        if let comp = board.components.first(where: { $0.id == id }) {
            let portIDs = Set(comp.ports.map { $0.id })
            board.wires.removeAll { portIDs.contains($0.fromPortID) || portIDs.contains($0.toPortID) }
        }
        board.components.removeAll { $0.id == id }
        if let tid = templates.keys.first(where: { id.hasPrefix($0 + "_") }) {
            placedCounts[tid] = max(0, (placedCounts[tid] ?? 1) - 1)
        }
        resetResult()
    }

    func removeLastWire() { if !board.wires.isEmpty { board.wires.removeLast(); resetResult() } }
    func clearWires() { board.wires.removeAll(); resetResult() }

    func resetResult() { result = nil; showResult = false; liveAnalysis = nil }

    func tapPort(_ id: String) {
        measurement = nil
        switch tool {
        case .wire, .stripper, .screwdriver:
            if let sel = selectedPort {
                if sel == id { selectedPort = nil }
                else { addWire(from: sel, to: id); selectedPort = nil }
            } else {
                selectedPort = id
            }
        case .multimeter:
            if let sel = selectedPort {
                let v = solver.measureVoltage(board, sel, id)
                measurement = "მულტიმეტრი: \(Int(v)) V"
                selectedPort = nil
            } else {
                selectedPort = id
                measurement = "აირჩიე მეორე წერტილი…"
            }
        case .voltageTester:
            measurement = solver.isLive(board, id) ? "⚡️ ფაზაა (ცხელი)" : "ფაზა არ არის"
            selectedPort = nil
        }
    }

    private func addWire(from: String, to: String) {
        guard from != to else { return }
        if board.wires.contains(where: {
            ($0.fromPortID == from && $0.toPortID == to) ||
            ($0.fromPortID == to && $0.toPortID == from)
        }) { return }
        let conductor = board.port(from)?.conductor ?? board.port(to)?.conductor ?? .L
        board.connect(from, to, csaMm2: selectedCSA, color: WireColor.standard(for: conductor))
        resetResult()
    }

    func check() {
        result = solver.solve(board, energize: false)
        liveAnalysis = nil
        showResult = true
    }

    func powerOn(game: GameState) {
        let r = solver.solve(board, energize: true)
        result = r
        liveAnalysis = solver.analyze(board)
        showResult = true
        if goalMet(r) {
            levelPassed = true
            game.markCompleted(level)
        }
    }

    func goalMet(_ r: SimulationResult) -> Bool {
        guard r.passed else { return false }
        for (kindStr, count) in level.goal.poweredLoads {
            guard let kind = ComponentKind(rawValue: kindStr) else { return false }
            let lit = board.components
                .filter { $0.kind == kind }
                .filter { r.state(for: $0.id)?.isPowered == true }
                .count
            if lit < count { return false }
        }
        return true
    }

    func isLive(_ portID: String) -> Bool {
        (liveAnalysis?.portConductors[portID] ?? []).contains { $0.isHot }
    }

    var csaOptions: [Double] {
        let all = level.palette.compactMap { $0.csaOptions }.flatMap { $0 }
        return all.isEmpty ? [1.5, 2.5, 4, 6, 10] : Array(Set(all)).sorted()
    }
}

// MARK: - Anchor preference for wiring

struct PortAnchorKey: PreferenceKey {
    static let defaultValue: [String: Anchor<CGPoint>] = [:]
    static func reduce(value: inout [String: Anchor<CGPoint>],
                       nextValue: () -> [String: Anchor<CGPoint>]) {
        value.merge(nextValue()) { $1 }
    }
}

// MARK: - Workbench view

struct WorkbenchView: View {
    @EnvironmentObject var game: GameState
    @StateObject private var model: WorkbenchModel
    @State private var showHint = false

    init(level: Level) {
        _model = StateObject(wrappedValue: WorkbenchModel(level: level, templates: [:]))
    }

    var body: some View {
        VStack(spacing: 0) {
            briefBar
            railView
            if let m = model.measurement {
                Text(m)
                    .font(.callout.bold())
                    .padding(8)
                    .frame(maxWidth: .infinity)
                    .background(Color.yellow.opacity(0.2))
            }
            Divider()
            controls
        }
        .navigationTitle(model.level.title)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                Button { showHint = true } label: { Image(systemName: "questionmark.circle") }
            }
        }
        .onAppear { if model.templates.isEmpty { model.templates = game.templates } }
        .sheet(isPresented: $model.showResult) {
            if let r = model.result {
                ResultPanelView(result: r, passed: model.levelPassed, level: model.level)
            }
        }
        .alert("მინიშნება", isPresented: $showHint) {
            Button("გასაგებია", role: .cancel) {}
        } message: { Text(model.level.hint) }
    }

    private var briefBar: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(model.level.brief)
                .font(.footnote)
                .foregroundStyle(.secondary)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal).padding(.vertical, 8)
        .background(Color(.secondarySystemBackground))
    }

    private var railView: some View {
        ScrollView([.horizontal, .vertical]) {
            HStack(alignment: .top, spacing: 28) {
                ForEach(model.board.components) { comp in
                    ComponentCardView(
                        component: comp,
                        selectedPort: model.selectedPort,
                        loadState: model.result?.state(for: comp.id),
                        isLive: { model.isLive($0) },
                        onTapPort: { model.tapPort($0) },
                        onDelete: comp.id == "supply" ? nil : { model.removeComponent(comp.id) }
                    )
                }
            }
            .padding(40)
            .backgroundPreferenceValue(PortAnchorKey.self) { anchors in
                GeometryReader { proxy in
                    ForEach(model.board.wires) { wire in
                        if let a = anchors[wire.fromPortID], let b = anchors[wire.toPortID] {
                            Path { p in
                                p.move(to: proxy[a])
                                p.addLine(to: proxy[b])
                            }
                            .stroke(wire.color.swiftUIColor,
                                    style: StrokeStyle(lineWidth: 4, lineCap: .round))
                        }
                    }
                }
            }
        }
        .frame(maxHeight: .infinity)
        .background(Color(.systemBackground))
    }

    private var controls: some View {
        VStack(spacing: 10) {
            // ხელსაწყოები
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(Tool.allCases) { t in
                        Button { model.tool = t; model.selectedPort = nil } label: {
                            Label(t.title, systemImage: t.symbol)
                                .font(.caption2)
                                .padding(.horizontal, 10).padding(.vertical, 6)
                                .background(model.tool == t ? Color.yellow.opacity(0.3) : Color(.secondarySystemBackground))
                                .clipShape(Capsule())
                        }
                        .buttonStyle(.plain)
                    }
                }.padding(.horizontal)
            }

            Text(model.tool.hint).font(.caption2).foregroundStyle(.secondary)

            // კომპონენტების პალიტრა
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(model.level.palette) { e in
                        let t = model.templates[e.templateId]
                        Button { model.add(e) } label: {
                            VStack(spacing: 2) {
                                Image(systemName: (t?.kind ?? .mcb).sfSymbol)
                                Text(t?.name ?? e.templateId).font(.caption2).lineLimit(1)
                                Text("\(model.placed(e.templateId))/\(e.max)").font(.caption2).foregroundStyle(.secondary)
                            }
                            .padding(8)
                            .frame(width: 96)
                            .background(Color(.secondarySystemBackground))
                            .clipShape(RoundedRectangle(cornerRadius: 10))
                        }
                        .buttonStyle(.plain)
                        .disabled(!model.canAdd(e))
                        .opacity(model.canAdd(e) ? 1 : 0.4)
                    }
                }.padding(.horizontal)
            }

            // კაბელის კვეთა
            HStack {
                Text("კვეთა:").font(.caption)
                Picker("კვეთა", selection: $model.selectedCSA) {
                    ForEach(model.csaOptions, id: \.self) { csa in
                        Text("\(csa, specifier: "%.1f")mm²").tag(csa)
                    }
                }
                .pickerStyle(.menu)
                Spacer()
                Button { model.removeLastWire() } label: { Image(systemName: "arrow.uturn.backward") }
                Button(role: .destructive) { model.clearWires() } label: { Image(systemName: "trash") }
            }.padding(.horizontal)

            // მოქმედებები
            HStack(spacing: 12) {
                Button { model.check() } label: {
                    Label("შემოწმება", systemImage: "checkmark.seal")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)

                Button { model.powerOn(game: game) } label: {
                    Label("ჩართე ძაბვა", systemImage: "bolt.fill")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .tint(.yellow)
            }.padding(.horizontal)
        }
        .padding(.vertical, 10)
        .background(Color(.secondarySystemBackground))
    }
}

// MARK: - Component card

struct ComponentCardView: View {
    let component: Component
    let selectedPort: String?
    let loadState: LoadState?
    let isLive: (String) -> Bool
    let onTapPort: (String) -> Void
    let onDelete: (() -> Void)?

    private var inputs: [Port] { component.ports.filter { $0.side == .input } }
    private var outputs: [Port] { component.ports.filter { $0.side == .output } }
    private var singles: [Port] { component.ports.filter { $0.side == .single } }

    var body: some View {
        VStack(spacing: 6) {
            ZStack {
                RoundedRectangle(cornerRadius: 10)
                    .fill(headerColor)
                    .frame(width: 92, height: 54)
                VStack(spacing: 2) {
                    Image(systemName: component.kind.sfSymbol)
                        .font(.title3)
                        .foregroundStyle(iconColor)
                    Text(component.name).font(.caption2).lineLimit(2).multilineTextAlignment(.center)
                }.padding(2)
            }
            .overlay(alignment: .topTrailing) {
                if let onDelete {
                    Button(action: onDelete) {
                        Image(systemName: "minus.circle.fill")
                            .foregroundStyle(.red).background(Circle().fill(.white))
                    }
                    .offset(x: 6, y: -6)
                }
            }

            HStack(alignment: .top, spacing: 14) {
                if !inputs.isEmpty { portColumn(inputs, label: "IN") }
                if !outputs.isEmpty { portColumn(outputs, label: "OUT") }
                if !singles.isEmpty { portColumn(singles, label: nil) }
            }
        }
        .padding(8)
        .background(RoundedRectangle(cornerRadius: 12).fill(Color(.tertiarySystemBackground)))
        .overlay(RoundedRectangle(cornerRadius: 12).stroke(Color.gray.opacity(0.3)))
    }

    private func portColumn(_ ports: [Port], label: String?) -> some View {
        VStack(spacing: 6) {
            if let label { Text(label).font(.system(size: 8)).foregroundStyle(.secondary) }
            ForEach(ports) { port in
                HStack(spacing: 4) {
                    portDot(port)
                    Text(port.name).font(.system(size: 9)).foregroundStyle(.secondary)
                }
                .contentShape(Rectangle())
                .onTapGesture { onTapPort(port.id) }
            }
        }
    }

    private func portDot(_ port: Port) -> some View {
        let selected = selectedPort == port.id
        let live = isLive(port.id)
        return Circle()
            .fill(port.conductor.swiftUIColor)
            .frame(width: 16, height: 16)
            .overlay(Circle().stroke(selected ? Color.yellow : Color.white,
                                     lineWidth: selected ? 3 : 1))
            .overlay {
                if live {
                    Circle().stroke(Color.yellow, lineWidth: 2).blur(radius: 2)
                }
            }
            .anchorPreference(key: PortAnchorKey.self, value: .center) { [port.id: $0] }
    }

    private var headerColor: Color {
        if let st = loadState {
            if st.trip != nil { return Color.red.opacity(0.25) }
            if st.isPowered { return Color.yellow.opacity(0.45) }
        }
        return Color(.secondarySystemBackground)
    }

    private var iconColor: Color {
        if let st = loadState {
            if st.trip != nil { return .red }
            if st.isPowered { return .orange }
        }
        return component.kind == .supply ? .yellow : .primary
    }
}
