//
//  CircuitSolverTests.swift
//  ElectricSimTests
//
//  Circuit solver-ის ერთეულ-ტესტები: ვალიდაცია + დეფექტების სცენარები
//  (short / overload / leakage / polarity / PE / ampacity).
//

import XCTest
@testable import ElectricSimCore

final class CircuitSolverTests: XCTestCase {

    let solver = CircuitSolver()

    // MARK: - Builders

    /// აშენებს ერთფაზიან ხაზს: კვება → მთავარი → [rcd?] → ავტომატი → დატვირთვა.
    /// აბრუნებს Board-ს. პარამეტრებით ვმართავთ დეფექტებს ტესტებში.
    private func makeLineBoard(load: Component,
                              breakerRating: Double = 10,
                              breakerCurve: BreakerCurve = .B,
                              csa: Double = 1.5,
                              includeRCD: Bool = false,
                              wireNeutral: Bool = true,
                              wirePE: Bool = true,
                              swapPolarity: Bool = false,
                              shortLN: Bool = false) -> Board {
        var board = Board(phase: .single)
        let supply = ComponentFactory.supply(id: "S")
        let main = ComponentFactory.mainSwitch(id: "MS")
        let mcb = ComponentFactory.mcb(id: "B", ratingA: breakerRating, curve: breakerCurve)
        board.add(supply); board.add(main); board.add(mcb); board.add(load)

        let rcd = ComponentFactory.rcd(id: "RCD")
        if includeRCD { board.add(rcd) }

        func p(_ comp: String, _ s: String) -> String { "\(comp).\(s)" }

        // --- ფაზა L ---
        board.connect(p("S", "L"), p("MS", "Lin"), csaMm2: csa, color: .brown)
        if includeRCD {
            board.connect(p("MS", "Lout"), p("RCD", "Lin"), csaMm2: csa, color: .brown)
            board.connect(p("RCD", "Lout"), p("B", "in"), csaMm2: csa, color: .brown)
        } else {
            board.connect(p("MS", "Lout"), p("B", "in"), csaMm2: csa, color: .brown)
        }
        let loadLineTerm = swapPolarity ? p(load.id, "N") : p(load.id, "L")
        board.connect(p("B", "out"), loadLineTerm, csaMm2: csa, color: .brown)

        // --- ნული N ---
        if wireNeutral {
            board.connect(p("S", "N"), p("MS", "Nin"), csaMm2: csa, color: .blue)
            let loadNeutralTerm = swapPolarity ? p(load.id, "L") : p(load.id, "N")
            if includeRCD {
                board.connect(p("MS", "Nout"), p("RCD", "Nin"), csaMm2: csa, color: .blue)
                board.connect(p("RCD", "Nout"), loadNeutralTerm, csaMm2: csa, color: .blue)
            } else {
                board.connect(p("MS", "Nout"), loadNeutralTerm, csaMm2: csa, color: .blue)
            }
        }

        // --- მიწა PE ---
        if wirePE {
            board.connect(p("S", "PE"), p(load.id, "PE"), csaMm2: csa, color: .yellowGreen)
        }

        // --- L–N მოკლე ჩართვა (განზრახ დეფექტი) ---
        if shortLN {
            board.connect(p(load.id, "L"), p(load.id, "N"), csaMm2: csa, color: .brown)
        }

        return board
    }

    // MARK: - 1. სწორი წრედი

    func testValidLampCircuit() {
        let lamp = ComponentFactory.lamp(id: "LAMP", powerW: 60)
        let board = makeLineBoard(load: lamp, breakerRating: 10, csa: 1.5)
        let result = solver.solve(board, energize: true)

        XCTAssertTrue(result.passed, "სწორი ხაზი უნდა გავიდეს ვალიდაცია: \(result.errors.map(\.code))")
        XCTAssertTrue(result.errors.isEmpty)
        let st = result.state(for: "LAMP")
        XCTAssertNotNil(st)
        XCTAssertTrue(st!.isPowered, "ნათურა უნდა აანთდეს")
        XCTAssertNil(st!.trip)
        XCTAssertEqual(st!.currentA, 60.0 / 230.0, accuracy: 0.001)
    }

    // MARK: - 2. როზეტი RCD-ის გარეშე

    func testSocketWithoutRCDFails() {
        let socket = ComponentFactory.socket(id: "SOC", powerW: 2300)
        let board = makeLineBoard(load: socket, breakerRating: 16, csa: 2.5, includeRCD: false)
        let result = solver.solve(board)
        XCTAssertFalse(result.passed)
        XCTAssertTrue(result.errors.contains { $0.code == .socketWithoutRCD })
    }

    // MARK: - 3. როზეტი RCD-ით

    func testSocketWithRCDPasses() {
        let socket = ComponentFactory.socket(id: "SOC", powerW: 2300)
        let board = makeLineBoard(load: socket, breakerRating: 16, csa: 2.5, includeRCD: true)
        let result = solver.solve(board, energize: true)
        XCTAssertTrue(result.passed, "RCD-ით როზეტი უნდა გავიდეს: \(result.errors.map(\.code))")
        XCTAssertFalse(result.errors.contains { $0.code == .socketWithoutRCD })
        XCTAssertTrue(result.state(for: "SOC")!.isPowered)
    }

    // MARK: - 4. მოკლე ჩართვა L–N

    func testShortCircuitLN() {
        let lamp = ComponentFactory.lamp(id: "LAMP")
        let board = makeLineBoard(load: lamp, shortLN: true)
        let result = solver.solve(board, energize: true)
        XCTAssertTrue(result.issues.contains { $0.code == .shortLN }, "უნდა აღმოაჩინოს L–N short")
        let st = result.state(for: "LAMP")!
        XCTAssertEqual(st.trip, .magnetic, "short → მაგნიტური გაგდება")
        XCTAssertFalse(st.isPowered)
    }

    // MARK: - 5. ავტომატი > კაბელის დასაშვები დენი

    func testBreakerExceedsCable() {
        let lamp = ComponentFactory.lamp(id: "LAMP")
        // B20 ავტომატი 1.5mm² კაბელზე (max 16A) → შეცდომა
        let board = makeLineBoard(load: lamp, breakerRating: 20, csa: 1.5)
        let result = solver.solve(board)
        XCTAssertTrue(result.errors.contains { $0.code == .breakerExceedsCable })
    }

    func testBreakerMatchesCablePasses() {
        let lamp = ComponentFactory.lamp(id: "LAMP")
        // B16 ავტომატი 2.5mm² კაბელზე (max 20A) → ოკ
        let board = makeLineBoard(load: lamp, breakerRating: 16, csa: 2.5)
        let result = solver.solve(board)
        XCTAssertFalse(result.errors.contains { $0.code == .breakerExceedsCable })
    }

    // MARK: - 6. გადატვირთვა (თერმული)

    func testOverloadThermalTrip() {
        // 3000W / 230V ≈ 13A, ავტომატი B10 → გადატვირთვა
        let lamp = ComponentFactory.lamp(id: "HEAT", powerW: 3000)
        let board = makeLineBoard(load: lamp, breakerRating: 10, csa: 2.5)
        let result = solver.solve(board, energize: true)
        let st = result.state(for: "HEAT")!
        XCTAssertEqual(st.trip, .thermal, "გადატვირთვა → თერმული გაგდება")
        XCTAssertTrue(result.issues.contains { $0.code == .overload })
        XCTAssertFalse(st.isPowered)
    }

    // MARK: - 7. დენის გაჟონვა (RCD)

    func testLeakageRCDTrip() {
        // 100mA გაჟონვა, RCD 30mA → RCD გაგდება
        let socket = ComponentFactory.socket(id: "SOC", powerW: 2300, leakageMa: 100)
        let board = makeLineBoard(load: socket, breakerRating: 16, csa: 2.5, includeRCD: true)
        let result = solver.solve(board, energize: true)
        let st = result.state(for: "SOC")!
        XCTAssertEqual(st.trip, .rcd, "გაჟონვა → RCD გაგდება")
        XCTAssertTrue(result.issues.contains { $0.code == .leakageTrip })
    }

    func testLeakageNoRCDShockRisk() {
        // 100mA გაჟონვა, RCD არ არის, PE არ არის → შოკის რისკი
        let lamp = ComponentFactory.lamp(id: "L1", powerW: 60, requiresPE: true, leakageMa: 100)
        let board = makeLineBoard(load: lamp, breakerRating: 10, csa: 1.5,
                                  includeRCD: false, wirePE: false)
        let result = solver.solve(board, energize: true)
        let st = result.state(for: "L1")!
        XCTAssertTrue(st.shockRisk, "მიწის გარეშე გაჟონვა → შოკის რისკი")
        XCTAssertTrue(result.issues.contains { $0.code == .shockRisk })
    }

    // MARK: - 8. მიწის (PE) გარეშე

    func testMissingPE() {
        let lamp = ComponentFactory.lamp(id: "LAMP", requiresPE: true)
        let board = makeLineBoard(load: lamp, wirePE: false)
        let result = solver.solve(board)
        XCTAssertTrue(result.errors.contains { $0.code == .missingPE })
    }

    // MARK: - 9. პოლარობის არევა

    func testPolarityReversed() {
        let lamp = ComponentFactory.lamp(id: "LAMP")
        let board = makeLineBoard(load: lamp, swapPolarity: true)
        let result = solver.solve(board)
        XCTAssertTrue(result.errors.contains { $0.code == .polarityReversed })
    }

    // MARK: - 10. ღია წრედი (ნული არ არის)

    func testOpenCircuitNoNeutral() {
        let lamp = ComponentFactory.lamp(id: "LAMP")
        let board = makeLineBoard(load: lamp, wireNeutral: false)
        let result = solver.solve(board, energize: true)
        XCTAssertTrue(result.errors.contains { $0.code == .openCircuit })
        XCTAssertFalse(result.state(for: "LAMP")!.isPowered, "ღია წრედზე ნათურა არ ანთდება")
    }

    // MARK: - 11. კვების გარეშე

    func testNoSupply() {
        var board = Board(phase: .single)
        board.add(ComponentFactory.lamp(id: "LAMP"))
        let result = solver.solve(board)
        XCTAssertTrue(result.issues.contains { $0.code == .noSupply })
    }

    // MARK: - 12. Ampacity ცხრილი

    func testAmpacityTable() {
        XCTAssertEqual(Ampacity.maxBreaker(forCsa: 1.5), 16)
        XCTAssertEqual(Ampacity.maxBreaker(forCsa: 2.5), 20)
        XCTAssertEqual(Ampacity.maxBreaker(forCsa: 4), 25)
        XCTAssertEqual(Ampacity.maxBreaker(forCsa: 6), 32)
        XCTAssertEqual(Ampacity.maxBreaker(forCsa: 10), 40)
        // 3.0mm² → უახლოესი ქვედა სტანდარტი (2.5) → 20A
        XCTAssertEqual(Ampacity.maxBreaker(forCsa: 3.0), 20)
    }

    // MARK: - Phase 2: fault-finding დონეები (prebuilt + fix)

    func testPhase2LevelsLoad() throws {
        let levels = try GameData.loadLevels()
        XCTAssertGreaterThanOrEqual(levels.count, 6)
        XCTAssertEqual(levels.first { $0.id == "lvl_fault_short" }?.resolvedMode, .faultFind)
        XCTAssertEqual(levels.first { $0.id == "lvl_tutorial" }?.resolvedMode, .build)
    }

    func testFaultOpenCircuitLevel() throws {
        let templates = try GameData.loadTemplates()
        let levels = try GameData.loadLevels()
        let level = try XCTUnwrap(levels.first { $0.id == "lvl_fault_open" })
        var board = level.initialBoard(templates: templates)

        // დეფექტი: ნათურა არ ანათებს (ნული აკლია)
        var r = solver.solve(board, energize: true)
        XCTAssertTrue(r.errors.contains { $0.code == .openCircuit })
        XCTAssertFalse(r.state(for: "lamp")!.isPowered)

        // შესწორება: დააკავშირე გამოტოვებული ნული
        board.connect("main.Nout", "lamp.N", csaMm2: 1.5, color: .blue)
        r = solver.solve(board, energize: true)
        XCTAssertTrue(r.passed, "გასწორების შემდეგ უნდა გაიაროს: \(r.errors.map(\.code))")
        XCTAssertTrue(r.state(for: "lamp")!.isPowered)
    }

    func testFaultShortLevel() throws {
        let templates = try GameData.loadTemplates()
        let levels = try GameData.loadLevels()
        let level = try XCTUnwrap(levels.first { $0.id == "lvl_fault_short" })
        var board = level.initialBoard(templates: templates)

        // დეფექტი: მოკლე ჩართვა → მაგნიტური გაგდება
        var r = solver.solve(board, energize: true)
        XCTAssertTrue(r.issues.contains { $0.code == .shortLN })
        XCTAssertEqual(r.state(for: "lamp")!.trip, .magnetic)

        // შესწორება: წაშალე ზედმეტი L–N სადენი
        board.wires.removeAll {
            ($0.fromPortID == "lamp.L" && $0.toPortID == "lamp.N") ||
            ($0.fromPortID == "lamp.N" && $0.toPortID == "lamp.L")
        }
        r = solver.solve(board, energize: true)
        XCTAssertTrue(r.passed)
        XCTAssertTrue(r.state(for: "lamp")!.isPowered)
    }

    func testFaultLeakageLevel() throws {
        let templates = try GameData.loadTemplates()
        let levels = try GameData.loadLevels()
        let level = try XCTUnwrap(levels.first { $0.id == "lvl_fault_leakage" })
        var board = level.initialBoard(templates: templates)

        // დეფექტი: გაუმართავი როზეტი აჟონავს → RCD იგდება
        var r = solver.solve(board, energize: true)
        XCTAssertEqual(r.state(for: "soc")!.trip, .rcd)

        // შესწორება: შეცვალე გაუმართავი როზეტი ახლით
        board.components.removeAll { $0.id == "soc" }
        board.wires.removeAll { $0.fromPortID.hasPrefix("soc.") || $0.toPortID.hasPrefix("soc.") }
        board.add(ComponentFactory.socket(id: "good", powerW: 2300))
        board.connect("brk.out", "good.L", csaMm2: 2.5, color: .brown)
        board.connect("rcd.Nout", "good.N", csaMm2: 2.5, color: .blue)
        board.connect("supply.PE", "good.PE", csaMm2: 2.5, color: .yellowGreen)

        r = solver.solve(board, energize: true)
        XCTAssertTrue(r.passed, "გასწორების შემდეგ უნდა გაიაროს: \(r.errors.map(\.code))")
        XCTAssertTrue(r.state(for: "good")!.isPowered)
    }

    // MARK: - Phase 3: სამფაზა + ბალანსი + მოტორი

    /// აშენებს სამფაზიან დაფას N ნათურით, თითო ფაზაზე `phaseFor(i)`.
    private func makeThreePhaseLamps(phaseFor: (Int) -> String, count: Int = 3) -> Board {
        var b = Board(phase: .three)
        b.add(ComponentFactory.supply(id: "S", phase: .three))
        let m = ComponentFactory.mainSwitch(id: "M", phase: .three)
        b.add(m)
        b.connect("S.N", "M.Nin", csaMm2: 2.5, color: .blue)
        for c in ["L1", "L2", "L3"] {
            b.connect("S.\(c)", "M.\(c)in", csaMm2: 2.5, color: .brown)
        }
        for i in 0..<count {
            let suf = phaseFor(i)
            let brk = ComponentFactory.mcb(id: "B\(i)", ratingA: 16)
            let lamp = ComponentFactory.lamp(id: "L\(i)", powerW: 60)
            b.add(brk); b.add(lamp)
            b.connect("M.\(suf)out", "B\(i).in", csaMm2: 2.5, color: .brown)
            b.connect("B\(i).out", "L\(i).L", csaMm2: 2.5, color: .brown)
            b.connect("M.Nout", "L\(i).N", csaMm2: 2.5, color: .blue)
            b.connect("S.PE", "L\(i).PE", csaMm2: 2.5, color: .yellowGreen)
        }
        return b
    }

    func testThreePhaseBalanced() {
        let board = makeThreePhaseLamps { ["L1", "L2", "L3"][$0] }
        let r = solver.solve(board, energize: true)
        XCTAssertTrue(r.passed, "დაბალანსებული დატვირთვა: \(r.errors.map(\.code))")
        XCTAssertFalse(r.issues.contains { $0.code == .phaseImbalance })
        XCTAssertTrue(r.loadStates.allSatisfy { $0.isPowered })
    }

    func testThreePhaseImbalance() {
        // სამივე ნათურა L1-ზე → დისბალანსი
        let board = makeThreePhaseLamps { _ in "L1" }
        let r = solver.solve(board, energize: true)
        XCTAssertTrue(r.warnings.contains { $0.code == .phaseImbalance })
    }

    func testMotorRuns() {
        var b = Board(phase: .three)
        b.add(ComponentFactory.supply(id: "S", phase: .three))
        b.add(ComponentFactory.mainSwitch(id: "M", phase: .three))
        b.add(ComponentFactory.mcb(id: "B", ratingA: 16, curve: .C))
        b.add(ComponentFactory.motor(id: "MOT", powerW: 4000))
        for c in ["L1", "L2", "L3"] { b.connect("S.\(c)", "M.\(c)in", csaMm2: 2.5, color: .brown) }
        b.connect("M.L1out", "B.in", csaMm2: 2.5, color: .brown)
        b.connect("B.out", "MOT.L1", csaMm2: 2.5, color: .brown)
        b.connect("M.L2out", "MOT.L2", csaMm2: 2.5, color: .black)
        b.connect("M.L3out", "MOT.L3", csaMm2: 2.5, color: .grey)
        b.connect("S.PE", "MOT.PE", csaMm2: 2.5, color: .yellowGreen)

        let r = solver.solve(b, energize: true)
        XCTAssertTrue(r.passed, "მოტორი უნდა ამუშავდეს: \(r.errors.map(\.code))")
        let st = r.state(for: "MOT")!
        XCTAssertTrue(st.isPowered)
        XCTAssertEqual(st.currentA, 4000.0 / (Double(3).squareRoot() * 400.0), accuracy: 0.05)
    }

    func testMotorMissingPhaseFails() {
        var b = Board(phase: .three)
        b.add(ComponentFactory.supply(id: "S", phase: .three))
        b.add(ComponentFactory.mainSwitch(id: "M", phase: .three))
        b.add(ComponentFactory.mcb(id: "B", ratingA: 16, curve: .C))
        b.add(ComponentFactory.motor(id: "MOT", powerW: 4000))
        for c in ["L1", "L2", "L3"] { b.connect("S.\(c)", "M.\(c)in", csaMm2: 2.5, color: .brown) }
        b.connect("M.L1out", "B.in", csaMm2: 2.5, color: .brown)
        b.connect("B.out", "MOT.L1", csaMm2: 2.5, color: .brown)
        b.connect("M.L2out", "MOT.L2", csaMm2: 2.5, color: .black)
        // L3 განზრახ არ არის
        b.connect("S.PE", "MOT.PE", csaMm2: 2.5, color: .yellowGreen)

        let r = solver.solve(b, energize: true)
        XCTAssertTrue(r.errors.contains { $0.code == .openCircuit })
        XCTAssertFalse(r.state(for: "MOT")!.isPowered)
    }

    func testPhase3LevelsLoad() throws {
        let templates = try GameData.loadTemplates()
        let levels = try GameData.loadLevels()
        let motorLevel = try XCTUnwrap(levels.first { $0.id == "lvl_motor" })
        XCTAssertEqual(motorLevel.phase, .three)
        XCTAssertEqual(levels.first { $0.id == "lvl_three_phase" }?.goal.requireBalanced, true)
        // მოტორის შაბლონი იქმნება სამი ფაზის ფეხებით
        let motor = templates["motor_3ph"]!.makeComponent(instanceID: "x")
        XCTAssertNotNil(motor.port(conductor: .L3))
    }

    // MARK: - 13. სადენის ფერები (IEC)

    func testWireColors() {
        XCTAssertEqual(WireColor.standard(for: .PE), .yellowGreen)
        XCTAssertEqual(WireColor.standard(for: .N), .blue)
        XCTAssertEqual(WireColor.standard(for: .L), .brown)
        XCTAssertEqual(WireColor.standard(for: .L1), .brown)
        XCTAssertEqual(WireColor.standard(for: .L2), .black)
        XCTAssertEqual(WireColor.standard(for: .L3), .grey)
    }
}
